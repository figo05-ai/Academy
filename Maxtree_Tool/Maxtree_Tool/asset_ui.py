"""UI for reviewed imports and manually organized local libraries."""
from pathlib import Path
import copy,uuid
from PySide6 import QtCore,QtGui,QtWidgets
from pymxs import runtime as rt
import collection_store as collections
from collection_ui import ScanJob,ReviewDialog
import asset_store as store
NOTE=('Organize your library into separate folders for Blocks/Models, Maps/Textures, and Preview Images. '
      'Keep each preview image\'s filename the same as its corresponding block/model filename (the extension can differ). '
      'Keep the map filenames intact so the model\'s material references can locate them.')
BUILD='General Blocks Update | 3.0'

def field(form,label,value='',folder=False,filter='All files (*.*)'):
    row=QtWidgets.QWidget();box=QtWidgets.QHBoxLayout(row);box.setContentsMargins(0,0,0,0)
    edit=QtWidgets.QLineEdit(value);button=QtWidgets.QPushButton('Browse...')
    def browse():
        path=QtWidgets.QFileDialog.getExistingDirectory(row,label,edit.text()) if folder else QtWidgets.QFileDialog.getOpenFileName(row,label,edit.text(),filter)[0]
        if path:edit.setText(path)
    button.clicked.connect(browse);box.addWidget(edit);box.addWidget(button);form.addRow(label,row)
    return edit

def buttons(dialog,layout,ok='OK'):
    bb=QtWidgets.QDialogButtonBox(QtWidgets.QDialogButtonBox.Ok|QtWidgets.QDialogButtonBox.Cancel)
    bb.button(QtWidgets.QDialogButtonBox.Ok).setText(ok);bb.accepted.connect(dialog.accept);bb.rejected.connect(dialog.reject);layout.addWidget(bb)

def pathcheck(text,folder=False,required=False):
    text=text.strip()
    if not text:
        if required:raise ValueError('A required path is empty.')
        return ''
    p=Path(text)
    if not p.is_absolute():raise ValueError('Select a full absolute path: '+text)
    if not (p.is_dir() if folder else p.is_file()):raise ValueError('Path not found: '+text)
    return str(p.resolve())

class CreateLibraryDialog(QtWidgets.QDialog):
    def __init__(self,parent=None,config=None):
        super().__init__(parent);self.setWindowTitle('Create Library');self.resize(790,530)
        layout=QtWidgets.QVBoxLayout(self);form=QtWidgets.QFormLayout();layout.addLayout(form)
        self.name=QtWidgets.QLineEdit();form.addRow('Library name',self.name)
        self.category=QtWidgets.QLineEdit('General');form.addRow('Category',self.category)
        self.workflow=QtWidgets.QComboBox();self.workflow.addItem('Import a structured asset library','structured');self.workflow.addItem('Create a manually organized library','manual');form.addRow('Choose workflow',self.workflow)
        self.kind=QtWidgets.QComboBox();self.kind.addItem('Maxtree (Corona only)','maxtree');self.kind.addItem('Generic indexed / filename-based library','generic');form.addRow('Structured library type',self.kind)
        self.renderer=QtWidgets.QComboBox();form.addRow('Renderer',self.renderer)
        self.method=QtWidgets.QComboBox();self.method.addItem('Add one block manually','single');self.method.addItem('Bulk-add from three folders','bulk');form.addRow('Manual method',self.method)
        self.root=field(form,'Structured library root',folder=True)
        self.models=field(form,'Blocks / Models folder',folder=True)
        self.maps=field(form,'Maps / Textures + optional MAT files',folder=True)
        self.previews=field(form,'Preview Images folder',folder=True)
        note=QtWidgets.QLabel(NOTE);note.setWordWrap(True);layout.addWidget(note)
        self.notice=QtWidgets.QLabel('Generic support: MAX files, exact preview basenames, or library/item XML indexes. Unknown structures require manual review. No source files are changed.');self.notice.setWordWrap(True);layout.addWidget(self.notice)
        buttons(self,layout,'Continue / Scan')
        self.workflow.currentIndexChanged.connect(self.refresh);self.kind.currentIndexChanged.connect(self.refresh);self.method.currentIndexChanged.connect(self.refresh)
        self.refresh()
        if config:
            self.workflow.setCurrentIndex(self.workflow.findData(config.get('workflow','manual')))
            self.kind.setCurrentIndex(self.kind.findData(config.get('library_type','generic')))
            self.method.setCurrentIndex(1)
            self.category.setText(config.get('category','General'))
            roles=config.get('folders',{})
            for edit,role in [(self.root,'mixed'),(self.models,'models'),(self.maps,'maps'),(self.previews,'previews')]:edit.setText(next(iter(roles.get(role,[])),''))
    def refresh(self,*_):
        structured=self.workflow.currentData()=='structured'
        for w in (self.kind,self.renderer):w.setEnabled(structured)
        self.root.parentWidget().setEnabled(structured)
        self.method.setEnabled(not structured)
        for w in (self.models,self.previews):w.parentWidget().setEnabled(not structured and self.method.currentData()=='bulk')
        self.maps.parentWidget().setEnabled(structured or self.method.currentData()=='bulk')
        if self.category.text() in ('General','Plants'):self.category.setText('Plants' if structured and self.kind.currentData()=='maxtree' else 'General')
        current=self.renderer.currentData();self.renderer.clear()
        if self.kind.currentData()=='maxtree':self.renderer.addItem('Corona verified variants only','corona')
        else:
            for name,value in [('Corona verified variants','corona'),('V-Ray named variants','vray'),('Embedded materials (review)','source')]:self.renderer.addItem(name,value)
        if self.renderer.findData(current)>=0:self.renderer.setCurrentIndex(self.renderer.findData(current))
    def config(self):
        if not self.name.text().strip():raise ValueError('Enter a library name.')
        structured=self.workflow.currentData()=='structured'
        cfg=dict(id='draft_'+uuid.uuid4().hex,workflow=self.workflow.currentData(),library_type=self.kind.currentData(),renderer=self.renderer.currentData() if structured else 'source',folders=dict(mixed=[],models=[],maps=[],previews=[]))
        cfg['category']=self.category.text().strip() or 'General'
        if structured:
            cfg['folders']['mixed']=[pathcheck(self.root.text(),True,True)]
            if self.maps.text().strip():cfg['folders']['maps']=[pathcheck(self.maps.text(),True)]
        elif self.method.currentData()=='bulk':
            for edit,role in [(self.models,'models'),(self.maps,'maps'),(self.previews,'previews')]:cfg['folders'][role]=[pathcheck(edit.text(),True,True)]
        return cfg

class AssetDialog(QtWidgets.QDialog):
    def __init__(self,parent=None,entry=None):
        super().__init__(parent);self.setWindowTitle('Add / Edit one block');self.resize(790,420)
        e=entry or {};layout=QtWidgets.QVBoxLayout(self);form=QtWidgets.QFormLayout();layout.addLayout(form)
        self.name=QtWidgets.QLineEdit(e.get('display_name',e.get('label','')));form.addRow('Display name',self.name)
        self.category=QtWidgets.QLineEdit(e.get('category','General'));form.addRow('Category',self.category)
        self.model=field(form,'Block / Model file',str(e.get('path','')),filter='3ds Max (*.max)')
        self.maps=field(form,'Maps folder override (blank = library)',e.get('maps_folder',''),folder=True)
        self.material=field(form,'Optional native material library',e.get('manual_material',''),filter='Material libraries (*.mat)')
        self.preview=field(form,'Preview image',e.get('explicit_preview',e.get('image','')),filter='Images (*.jpg *.jpeg *.png *.tif *.tiff *.bmp)')
        note=QtWidgets.QLabel(NOTE);note.setWordWrap(True);layout.addWidget(note)
        layout.addWidget(QtWidgets.QLabel('Existing model materials are preserved when no MAT file is selected.'))
        buttons(self,layout,'Scan / Review block')
    def values(self):
        model=pathcheck(self.model.text(),required=True)
        if Path(model).suffix.casefold()!='.max':raise ValueError('This importer supports MAX model files.')
        material=pathcheck(self.material.text())
        if material and Path(material).suffix.casefold()!='.mat':raise ValueError('The current importer supports native .MAT libraries only.')
        return dict(path=model,display_name=self.name.text().strip() or Path(model).stem,category=self.category.text().strip() or 'General',maps_folder=pathcheck(self.maps.text(),True),manual_material=material,explicit_preview=pathcheck(self.preview.text()))
    def config(self):
        e=self.values();parent=str(Path(e['path']).parent)
        return dict(id='draft_'+uuid.uuid4().hex,workflow='manual',renderer='source',category=e['category'],explicit_models=[e['path']],folders=dict(mixed=[],models=[parent],maps=[e['maps_folder']] if e['maps_folder'] else [],previews=[str(Path(e['explicit_preview']).parent)] if e['explicit_preview'] else [parent]))

class ScanReport(QtWidgets.QDialog):
    def __init__(self,result,parent=None,cid=None):
        super().__init__(parent);self.setWindowTitle('Review scan before adding');self.resize(1090,610)
        self.result=result;self.other=store.all_paths(cid);layout=QtWidgets.QVBoxLayout(self)
        entries=result['entries'];eligible=[e for e in entries if not e.get('excluded')]
        summary=dict(models=len(entries),eligible=len(eligible),missing=sum(not e.get('image') or any(m['status']=='missing' for m in e.get('map_matches',[])) for e in eligible),ambiguous=sum(any('ambiguous' in i.casefold() or 'Object review' in i for i in e.get('issues',[])) for e in eligible),previews=sum(bool(e.get('image')) for e in eligible),duplicates=sum(collections.identity(e['path']) in self.other for e in eligible),review=sum(bool(e.get('issues')) for e in eligible),resolved_maps=sum(m.get('status')=='resolved' for e in eligible for m in e.get('map_matches',[])),unresolved_maps=sum(m.get('status')!='resolved' for e in eligible for m in e.get('map_matches',[])))
        self.summary=summary
        self.label=QtWidgets.QLabel(' | '.join(k+': '+str(v) for k,v in summary.items()));self.label.setWordWrap(True);layout.addWidget(self.label)
        self.cross=QtWidgets.QCheckBox('Explicitly allow models already present in another library');layout.addWidget(self.cross)
        self.table=QtWidgets.QTableWidget(len(entries),5);self.table.setHorizontalHeaderLabels(['Add','Model / block','Preview','Maps','Review / exclusion']);layout.addWidget(self.table)
        for row,e in enumerate(entries):
            tick=QtWidgets.QTableWidgetItem();tick.setFlags(QtCore.Qt.ItemIsUserCheckable|QtCore.Qt.ItemIsEnabled)
            tick.setCheckState(QtCore.Qt.Unchecked if e.get('excluded') or collections.identity(e['path']) in self.other else QtCore.Qt.Checked)
            if e.get('excluded'):tick.setFlags(QtCore.Qt.NoItemFlags)
            self.table.setItem(row,0,tick)
            vals=[e.get('display_name',e['label']),e.get('image') or 'MISSING / REVIEW',str(sum(bool(m.get('resolved')) for m in e.get('map_matches',[])))+'/'+str(len(e.get('map_matches',[]))),e.get('excluded') or '; '.join(e.get('issues',[])) or 'Ready']
            for col,value in enumerate(vals,1):
                item=QtWidgets.QTableWidgetItem(value);item.setToolTip(e['path']+'\n'+value);self.table.setItem(row,col,item)
        self.table.horizontalHeader().setSectionResizeMode(QtWidgets.QHeaderView.ResizeToContents);self.table.horizontalHeader().setStretchLastSection(True)
        self.cross.toggled.connect(self.toggle_duplicates)
        review=QtWidgets.QPushButton('Review selected object / preview / maps...');review.clicked.connect(self.review);layout.addWidget(review)
        note=QtWidgets.QLabel('Unchecked and excluded models are not added. Multiple objects require explicit selection at insertion. Unsupported structures can be added with the manual workflow.');note.setWordWrap(True);layout.addWidget(note)
        buttons(self,layout,'Add confirmed models')
    def toggle_duplicates(self,on):
        for i,e in enumerate(self.result['entries']):
            if not e.get('excluded') and collections.identity(e['path']) in self.other:self.table.item(i,0).setCheckState(QtCore.Qt.Checked if on else QtCore.Qt.Unchecked)
    def review(self):
        row=self.table.currentRow()
        if row<0:return
        e=self.result['entries'][row]
        if e.get('excluded'):return
        d=ReviewDialog(e,self)
        if d.exec()==QtWidgets.QDialog.Accepted:
            try:
                e['review']=d.review();e.update(store.effective(e));self.table.item(row,4).setText('Explicit choices saved in draft')
            except Exception as exc:QtWidgets.QMessageBox.warning(self,'Review',str(exc))
    def selected(self):
        return [e for i,e in enumerate(self.result['entries']) if not e.get('excluded') and self.table.item(i,0).checkState()==QtCore.Qt.Checked and (self.cross.isChecked() or collections.identity(e['path']) not in self.other)]

class LibraryManager(QtWidgets.QDialog):
    def __init__(self,parent=None):
        super().__init__(parent);self.setWindowTitle('Libraries / Single Assets - '+BUILD);self.resize(1120,760)
        import maxtree_library as lib
        self.lib=lib;self.importer=lib.Importer();self.job=None;self.pending=None
        self.setWindowTitle(lib.TITLE+' | Libraries')
        layout=QtWidgets.QVBoxLayout(self);self.build=QtWidgets.QLabel(BUILD);layout.addWidget(self.build)
        row=QtWidgets.QHBoxLayout();self.libraries=QtWidgets.QComboBox();row.addWidget(self.libraries,1)
        actions=[('Create Library',self.create_library),('Rename',self.rename),('Rescan',self.rescan),('Remove library',self.remove_library),('Library maps...',self.library_maps)]
        self.actions={}
        for label,fn in actions:
            b=QtWidgets.QPushButton(label);b.clicked.connect(fn);row.addWidget(b);self.actions[label]=b
        layout.addLayout(row)
        row=QtWidgets.QHBoxLayout()
        for label,fn in [('Add Single Block',self.add_single),('Add one block',self.add_block),('Bulk-add three folders',self.bulk),('Edit / Replace',self.edit),('Remove entry',self.remove_entry),('Review matches',self.review)]:
            b=QtWidgets.QPushButton(label);b.clicked.connect(fn);row.addWidget(b);self.actions[label]=b
        layout.addLayout(row)
        self.category_filter=QtWidgets.QComboBox();self.category_filter.addItem('All categories');layout.addWidget(self.category_filter)
        self.category_filter.currentTextChanged.connect(self.filter)
        self.search=QtWidgets.QLineEdit();self.search.setPlaceholderText('Search name, category, library or variant');layout.addWidget(self.search)
        body=QtWidgets.QHBoxLayout();self.list=QtWidgets.QListWidget();self.list.setIconSize(QtCore.QSize(90,90));body.addWidget(self.list,2)
        side=QtWidgets.QVBoxLayout();self.preview=QtWidgets.QLabel();self.preview.setMinimumSize(280,280);self.preview.setAlignment(QtCore.Qt.AlignCenter);side.addWidget(self.preview)
        self.details=QtWidgets.QLabel();self.details.setWordWrap(True);side.addWidget(self.details)
        self.variant=QtWidgets.QComboBox();side.addWidget(self.variant);side.addStretch();body.addLayout(side,1);layout.addLayout(body,1)
        pages=QtWidgets.QHBoxLayout();self.prev=QtWidgets.QPushButton('Previous');self.next=QtWidgets.QPushButton('Next');self.count=QtWidgets.QLabel();self.page=0
        for w in (self.prev,self.count,self.next):pages.addWidget(w)
        layout.addLayout(pages);self.prev.clicked.connect(lambda:self.turn(-1));self.next.clicked.connect(lambda:self.turn(1))
        row=QtWidgets.QHBoxLayout();self.mode=QtWidgets.QComboBox();self.mode.addItems(['Copy','Instance']);self.insert=QtWidgets.QPushButton('Insert selected block');self.cancel=QtWidgets.QPushButton('Cancel scan')
        self.placement=QtWidgets.QComboBox();self.placement.addItems(['Origin (0,0,0)','Selected object pivot'])
        for w in (self.mode,self.placement,self.insert,self.cancel):row.addWidget(w)
        layout.addLayout(row)
        self.undo_note=QtWidgets.QLabel('Native Undo does not reliably remove a complete insertion in this build. Inserted blocks remain selected for manual deletion.');self.undo_note.setWordWrap(True);layout.addWidget(self.undo_note)
        self.status=QtWidgets.QLabel('Local paths only. Select a library.');self.status.setWordWrap(True);layout.addWidget(self.status)
        self.libraries.currentIndexChanged.connect(self.filter);self.search.textChanged.connect(self.filter);self.list.currentItemChanged.connect(self.select)
        self.variant.currentIndexChanged.connect(self.variant_preview)
        self.insert.clicked.connect(self.insert_selected);self.cancel.clicked.connect(lambda:self.job.cancel() if self.job else None)
        import block_actions
        block_actions.attach(self.list,self,self.selected,self.refresh)
        self.refresh()
    def cid(self):return self.libraries.currentData() or store.SINGLE
    def refresh(self,cid=None):
        self.search.blockSignals(True);self.search.clear();self.search.blockSignals(False)
        cid=cid or self.cid();self.libraries.blockSignals(True);self.libraries.clear()
        for c in store.libraries():self.libraries.addItem(c['name'],c['id'])
        ix=self.libraries.findData(cid);self.libraries.setCurrentIndex(max(0,ix));self.libraries.blockSignals(False);self.filter()
    def filter(self,*_):
        c=store.get(self.cid());q=self.search.text().casefold();rows=[];errors=[]
        for raw in c.get('entries',[]):
            try:
                e=store.effective(raw);e.setdefault('category',c.get('config',{}).get('category','General'));rows.append(e)
            except Exception as exc:errors.append(str(exc))
        old=self.category_filter.currentText();self.category_filter.blockSignals(True);self.category_filter.clear()
        self.category_filter.addItems(['All categories']+sorted({e['category'] for e in rows}));ix=self.category_filter.findText(old);self.category_filter.setCurrentIndex(max(0,ix));self.category_filter.blockSignals(False)
        cat=self.category_filter.currentText()
        self.filtered=[e for e in rows if (cat=='All categories' or e['category']==cat) and q in (' '.join([e.get('display_name',''),e['label'],Path(e['path']).stem,c['name'],e['category'],' '.join(e.get('object_names',[]))]+[v['name'] for v in e.get('variants',[])])).casefold()]
        self.page=0;self.populate()
        if errors:self.status.setText('Skipped invalid block references: '+'; '.join(errors))
    def turn(self,d):self.page=max(0,min(self.page+d,max(0,(len(self.filtered)-1)//12)));self.populate()
    def populate(self):
        self.list.clear();self.preview.clear();self.details.clear();self.variant.clear()
        for e in self.filtered[self.page*12:(self.page+1)*12]:
            item=QtWidgets.QListWidgetItem(e['label']+'\n'+store.get(self.cid())['name']);item.setData(QtCore.Qt.UserRole,e)
            pix=self.lib.preview_pixmap(e.get('image'),QtCore.QSize(90,90))
            if not pix.isNull():item.setIcon(QtGui.QIcon(pix))
            else:item.setText(item.text()+' [Preview missing]')
            self.list.addItem(item)
        self.count.setText(str(len(self.filtered))+' models | page '+str(self.page+1));self.prev.setEnabled(self.page>0);self.next.setEnabled((self.page+1)*12<len(self.filtered))
        for b in self.actions.values():b.setEnabled(not self.job)
        self.actions['Rename'].setEnabled(self.cid()!=store.SINGLE and not self.job);self.actions['Remove library'].setEnabled(self.cid()!=store.SINGLE and not self.job)
        self.cancel.setEnabled(bool(self.job));self.insert.setEnabled(False)
        if self.list.count():self.list.setCurrentRow(0)
    def selected(self):
        item=self.list.currentItem()
        if not item:return None
        e=item.data(QtCore.Qt.UserRole);c=store.get(self.cid());e['_images']=c.get('images',{});e['_ref_kind']='managed';e['_library_maps']=[c['maps_folder']] if c.get('maps_folder') else c.get('config',{}).get('folders',{}).get('maps',[]);return e
    def select(self,*_):
        e=self.selected()
        if not e:return
        self.variant.clear();self.variant.addItems([v['name'] for v in e.get('variants',[])])
        import block_actions
        self.details.setText(block_actions.details(e,store.get(self.cid())['name']))
        self.insert.setEnabled(Path(e['path']).is_file() and not self.job);self.variant_preview()
    def variant_preview(self,*_):
        e=self.selected()
        if not e:return
        i=self.variant.currentIndex();v=e.get('variants',[]);path=(v[i].get('image') if 0<=i<len(v) else '') or e.get('image')
        pix=self.lib.preview_pixmap(path,QtCore.QSize(330,330))
        if pix.isNull():self.preview.setText('Preview missing / requires review')
        else:self.preview.setPixmap(pix)
    def guarded(self,fn):
        try:return fn()
        except Exception as exc:QtWidgets.QMessageBox.warning(self,'Local library',str(exc))
    def create_library(self):
        d=CreateLibraryDialog(self)
        if d.exec()!=QtWidgets.QDialog.Accepted:return
        def go():
            cfg=d.config();name=d.name.text().strip()
            if cfg['workflow']=='manual' and d.method.currentData()=='single':
                a=AssetDialog(self)
                if a.exec()!=QtWidgets.QDialog.Accepted:return
                self.start(a.config(),dict(name=name,values=a.values()))
            else:self.start(cfg,dict(name=name))
        self.guarded(go)
    def add_single(self):self.add_block(single=True)
    def add_block(self,checked=False,single=False):
        d=AssetDialog(self)
        if d.exec()==QtWidgets.QDialog.Accepted:self.guarded(lambda:self.start(d.config(),dict(cid=store.SINGLE if single else self.cid(),values=d.values())))
    def edit(self):
        e=self.selected()
        if not e:return
        d=AssetDialog(self,e)
        if d.exec()==QtWidgets.QDialog.Accepted:self.guarded(lambda:self.start(d.config(),dict(cid=self.cid(),values=d.values(),replace_id=e['id'])))
    def bulk(self):
        d=CreateLibraryDialog(self);d.name.setText(store.get(self.cid())['name']);d.workflow.setCurrentIndex(1);d.method.setCurrentIndex(1)
        if d.exec()==QtWidgets.QDialog.Accepted:self.guarded(lambda:self.start(d.config(),dict(cid=self.cid())))
    def start(self,cfg,pending):
        if self.job:raise RuntimeError('A scan is already running.')
        self.pending=dict(pending,config=cfg);self.job=ScanJob(cfg['id'],self,config=cfg)
        self.job.progress.connect(self.status.setText);self.job.finished.connect(self.scanned);self.job.start();self.populate()
    def scanned(self,cid,ok,message):
        job=self.job;self.job=None;pending=self.pending;self.pending=None;self.status.setText(message)
        if not ok:job.retire();self.populate();return
        result=collections.read_json(job.folder/'result.json');job.retire()
        for e in result['entries']:
            e['renderer_policy']='corona' if pending['config'].get('renderer')=='corona' else 'source'
            if pending.get('values'):
                e.update(pending['values']);e['image']=e.get('explicit_preview') or e.get('image','')
                if e.get('explicit_preview'):
                    e['issues']=[x for x in e['issues'] if 'preview' not in x.casefold()]
                    for v in e['variants']:v['image']=e['image']
            if pending['config'].get('workflow')=='manual':
                for v in e['variants']:v['matlib']='';v['material']=''
        report=ScanReport(result,self,pending.get('cid'));self.last_report=report
        if report.exec()==QtWidgets.QDialog.Accepted:
            selected=report.selected()
            if selected:
                try:
                    target=pending.get('cid') or store.create(pending['name'],pending['config'])['id']
                    config=pending['config'] if not pending.get('values') else None
                    store.put(target,selected,config=config,replace_id=pending.get('replace_id'))
                    c=store.get(target);images=c.get('images',{})
                    for k,values in result.get('images',{}).items():images[k]=list(dict.fromkeys(images.get(k,[])+values))
                    store.update(target,images=images)
                    self.refresh(target);self.status.setText('Added / updated '+str(len(selected))+' references. No source files changed.')
                except Exception as exc:QtWidgets.QMessageBox.warning(self,'Add models',str(exc))
            else:self.status.setText('No models confirmed. Nothing added; use the manual workflow for unsupported structures.')
        self.populate()
    def rescan(self):
        c=store.get(self.cid());cfg=copy.deepcopy(c.get('config',{}))
        if cfg and not cfg.get('explicit_models') and any(cfg.get('folders',{}).get(k) for k in ('models','mixed')):
            d=CreateLibraryDialog(self,cfg);d.name.setText(c['name'])
            if d.exec()==QtWidgets.QDialog.Accepted:self.guarded(lambda:self.start(d.config(),dict(cid=self.cid())))
        else:
            e=self.selected()
            if e:
                d=AssetDialog(self,e);self.guarded(lambda:self.start(d.config(),dict(cid=self.cid(),values=d.values(),replace_id=e['id'])))
    def rename(self):
        if self.cid()==store.SINGLE:return
        name,ok=QtWidgets.QInputDialog.getText(self,'Rename library','Library name',text=store.get(self.cid())['name'])
        if ok:self.guarded(lambda:(store.update(self.cid(),name=name.strip()),self.refresh()))
    def remove_library(self):
        if self.cid()==store.SINGLE:return
        if QtWidgets.QMessageBox.question(self,'Remove library','Remove its references only? Source files remain untouched.')==QtWidgets.QMessageBox.Yes:
            store.update(self.cid(),removed=True);self.refresh()
    def remove_entry(self):
        e=self.selected()
        if e and QtWidgets.QMessageBox.question(self,'Remove entry','Remove this reference only?')==QtWidgets.QMessageBox.Yes:store.remove_entry(self.cid(),e['id']);self.refresh()
    def review(self):
        import block_actions
        block_actions.run(self,'Review Matches',self.selected(),self.refresh)
    def library_maps(self):
        folder=QtWidgets.QFileDialog.getExistingDirectory(self,'Library maps folder',store.get(self.cid()).get('maps_folder',''))
        if folder:
            if self.cid()==store.SINGLE and not any(c['id']==store.SINGLE for c in store.state()['libraries']):store.put(store.SINGLE,[])
            config=copy.deepcopy(store.get(self.cid()).get('config',{}));config.setdefault('folders',{})['maps']=[folder]
            store.update(self.cid(),maps_folder=folder,config=config);self.refresh()
    def insert_selected(self):
        e=self.selected()
        if not e:return
        try:
            node,maps,warnings=self.importer.insert(e,mode=self.mode.currentText(),variant_index=max(0,self.variant.currentIndex()),placement='selection' if self.placement.currentIndex()==1 else None,parent=self)
            self.last_insert=dict(name=str(node.name),maps=maps,material_assignments=e.get('_material_assignment',[]),summary=self.importer.last_summary)
            self.status.setText('Inserted '+str(node.name)+' | '+__import__('block_runtime').summary_text(e.get('_last_map_report',[])))
        except Exception as exc:
            self.status.setText(str(exc));QtWidgets.QMessageBox.warning(self,'Block insertion',str(exc)+'\nUse Review Matches to confirm candidates.')
        finally:
            if self.list.currentItem():self.list.currentItem().setData(QtCore.Qt.UserRole,e)
            self.select()
    def closeEvent(self,event):
        if self.job:self.job.cancel();event.ignore()
        else:event.accept()

def open_manager(parent,action=None):
    import maxtree_license_manager
    if not maxtree_license_manager.get_manager().is_authorized():
        QtWidgets.QMessageBox.warning(parent,'Access Locked','Access locked. Please log in with an active Maxtree license to manage libraries.')
        return None
    manager=getattr(parent,'asset_manager',None)
    if manager is None:manager=LibraryManager(parent);parent.asset_manager=manager
    manager.show();manager.raise_();manager.activateWindow()
    if action=='create':manager.create_library()
    elif action=='single':manager.add_single()
    return manager
