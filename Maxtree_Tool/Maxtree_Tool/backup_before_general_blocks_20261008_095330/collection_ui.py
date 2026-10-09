"""Dialogs and asynchronous scan jobs for local path references."""
from pathlib import Path
import uuid
from PySide6 import QtCore,QtWidgets
from pymxs import runtime as rt
import collection_store as store
_retired_jobs=[]

class CollectionDialog(QtWidgets.QDialog):
    def __init__(self,parent=None):
        super().__init__(parent)
        self.setWindowTitle('Create local collection')
        self.resize(700,480)
        layout=QtWidgets.QVBoxLayout(self)
        self.name=QtWidgets.QLineEdit()
        self.name.setPlaceholderText('Collection name — any language')
        layout.addWidget(self.name)
        layout.addWidget(QtWidgets.QLabel('Choose local folders. Only path references are saved; no assets are copied.'))
        self.folders=QtWidgets.QListWidget()
        layout.addWidget(self.folders)
        controls=QtWidgets.QHBoxLayout()
        self.role=QtWidgets.QComboBox()
        for label,value in [('Mixed assets','mixed'),('Models only','models'),('Previews only','previews'),('Maps only','maps')]:
            self.role.addItem(label,value)
        add=QtWidgets.QPushButton('Add folder…')
        remove=QtWidgets.QPushButton('Remove selected folder')
        controls.addWidget(self.role);controls.addWidget(add);controls.addWidget(remove)
        layout.addLayout(controls)
        self.renderer=QtWidgets.QComboBox()
        self.renderer.addItem('Require Corona materials','corona')
        self.renderer.addItem('Use embedded source materials (no conversion)','source')
        layout.addWidget(self.renderer)
        buttons=QtWidgets.QDialogButtonBox(QtWidgets.QDialogButtonBox.Ok|QtWidgets.QDialogButtonBox.Cancel)
        layout.addWidget(buttons)
        add.clicked.connect(self.add_folder)
        remove.clicked.connect(lambda:self.folders.takeItem(self.folders.currentRow()))
        buttons.accepted.connect(self.accept);buttons.rejected.connect(self.reject)
    def add_folder(self):
        folder=QtWidgets.QFileDialog.getExistingDirectory(self,'Select local asset folder')
        if folder:
            item=QtWidgets.QListWidgetItem(self.role.currentText()+': '+folder)
            item.setData(QtCore.Qt.UserRole,(self.role.currentData(),folder))
            self.folders.addItem(item)
    def create(self):
        roles={k:[] for k in ('mixed','models','previews','maps')}
        for i in range(self.folders.count()):
            role,path=self.folders.item(i).data(QtCore.Qt.UserRole)
            roles[role].append(path)
        return store.create_collection(self.name.text(),roles,self.renderer.currentData())

class ScanJob(QtCore.QObject):
    finished=QtCore.Signal(str,bool,str)
    progress=QtCore.Signal(str)
    def __init__(self,cid,parent=None,config=None):
        super().__init__(parent)
        self.cid=cid
        self.config=config
        self.folder=store.SETTINGS/'jobs'/uuid.uuid4().hex
        self.process=QtCore.QProcess(self)
        self.timer=QtCore.QTimer(self)
        self.timer.setInterval(600)
        self.timer.timeout.connect(self.poll)
        self.process.finished.connect(self.done)
        self.process.errorOccurred.connect(self.error)
        self.ended=False
        self.cancelled=False
    def start(self):
        store.atomic_json(self.folder/'job.json',{'collection':self.config if self.config is not None else store.get_collection(self.cid)})
        py="import sys; sys.path.insert(0, "+repr(str(store.PROJECT))+"); import collection_scan; collection_scan.run("+repr(str(self.folder/'job.json'))+")"
        runner=self.folder/'run.py'
        runner.write_text(py,encoding='utf-8')
        launcher=self.folder/'scan.ms'
        launcher.write_text('python.ExecuteFile @"'+runner.as_posix()+'"',encoding='utf-8')
        exe=Path(str(rt.getDir(rt.Name('maxroot'))))/'3dsmaxbatch.exe'
        if not exe.is_file():
            raise RuntimeError('3dsmaxbatch.exe not found: '+str(exe))
        self.process.setProgram(str(exe))
        self.process.setArguments([str(launcher),'-log',str(self.folder/'max.log')])
        self.process.setProcessChannelMode(QtCore.QProcess.MergedChannels)
        self.process.readyReadStandardOutput.connect(lambda:self.process.readAllStandardOutput())
        self.process.start()
        self.timer.start()
        self.progress.emit('Starting isolated scan process…')
    def poll(self):
        if self.ended: return
        if (self.folder/'result.json').is_file() or (self.folder/'error.json').is_file():
            self.done()
            return
        p=self.folder/'progress.json'
        if p.is_file():
            try:
                d=store.read_json(p)
                self.progress.emit('Scanning %s / %s — %s'%(d.get('processed',0),d.get('total','?'),Path(d.get('path','')).name))
            except (ValueError,OSError):
                pass
    def retire(self):
        self.setParent(None)
        _retired_jobs.append(self)
        def release(*args):
            if self in _retired_jobs: _retired_jobs.remove(self)
            self.deleteLater()
        if self.process.state()==QtCore.QProcess.NotRunning:
            release()
        else:
            self.process.finished.connect(release)

    def error(self,*_):
        if self.process.error()==QtCore.QProcess.FailedToStart:
            self.finish(False,self.process.errorString())
    def cancel(self):
        self.cancelled=True
        store.atomic_json(self.folder/'cancel.json', {'cancel':True})
        self.progress.emit('Cancelling after the current file…')
    def kill_if_running(self):
        if self.process.state()!=QtCore.QProcess.NotRunning:
            self.process.kill()
    def done(self,*_):
        if self.ended: return
        result=self.folder/'result.json'
        if self.cancelled:
            self.finish(False,'Scan cancelled; previous catalog retained.')
        elif result.is_file():
            try:
                if self.config is None: store.commit_scan(self.cid,result)
                self.finish(True,'Scan complete.')
            except Exception as exc:
                self.finish(False,str(exc))
        else:
            error=self.folder/'error.json'
            self.finish(False,store.read_json(error)['error'] if error.is_file() else 'Scan process ended without a result. See '+str(self.folder/'max.log'))
    def finish(self,ok,message):
        if not self.ended:
            self.ended=True
            self.timer.stop()
            self.finished.emit(self.cid,ok,message)

class ReviewDialog(QtWidgets.QDialog):
    def __init__(self,entry,parent=None):
        super().__init__(parent)
        self.e=entry
        self.setWindowTitle('Review local asset matches')
        self.resize(850,650)
        layout=QtWidgets.QVBoxLayout(self)
        text=QtWidgets.QLabel(str(entry['path'])+'\n'+'\n'.join(entry.get('issues',[])[:5]))
        text.setWordWrap(True)
        layout.addWidget(text)
        scroll=QtWidgets.QScrollArea()
        scroll.setWidgetResizable(True)
        panel=QtWidgets.QWidget()
        form=QtWidgets.QFormLayout(panel)
        scroll.setWidget(panel)
        layout.addWidget(scroll)
        self.object=QtWidgets.QComboBox()
        self.object.addItem('Keep indexed variants / unresolved','')
        for name in entry.get('object_names',[]):
            self.object.addItem(name,name)
        current=entry.get('review',{}).get('object')
        if current: self.object.setCurrentIndex(self.object.findData(current))
        form.addRow('Exact plant object',self.object)
        self.preview=self.path_selector(form,'Preview',entry.get('preview_candidates',[]),
            entry.get('review',{}).get('preview') or str(entry.get('image') or ''))
        self.matlib=self.path_selector(form,'Optional material library',entry.get('material_libraries',[]),
            entry.get('review',{}).get('matlib',''),file_filter='Material libraries (*.mat)')
        self.material=QtWidgets.QLineEdit(entry.get('review',{}).get('material',''))
        self.material.setPlaceholderText('Exact material name; blank uses imported material name')
        form.addRow('Material name',self.material)
        self.maps=[]
        for match in entry.get('map_matches',[]):
            old=match['reference']
            combo=self.path_selector(form,Path(old.replace('\\','/')).name+' ['+match['status']+']',
                match['candidates'],entry.get('map_overrides',{}).get(old) or match.get('resolved',''))
            self.maps.append((old,combo))
        buttons=QtWidgets.QDialogButtonBox(QtWidgets.QDialogButtonBox.Save|QtWidgets.QDialogButtonBox.Cancel)
        layout.addWidget(buttons)
        buttons.accepted.connect(self.accept);buttons.rejected.connect(self.reject)
    def path_selector(self,form,label,paths,current,file_filter='Images (*.*)'):
        row=QtWidgets.QWidget()
        horizontal=QtWidgets.QHBoxLayout(row);horizontal.setContentsMargins(0,0,0,0)
        combo=QtWidgets.QComboBox();combo.setMinimumContentsLength(35)
        combo.setSizeAdjustPolicy(QtWidgets.QComboBox.AdjustToMinimumContentsLengthWithIcon)
        combo.addItem('Unresolved / use existing link','')
        for path in dict.fromkeys([current]+list(paths)):
            if path: combo.addItem(path,path)
        if current: combo.setCurrentIndex(combo.findData(current))
        choose=QtWidgets.QPushButton('Browse…')
        def browse():
            path,_=QtWidgets.QFileDialog.getOpenFileName(self,'Choose local file','',file_filter)
            if path:
                if combo.findData(path)<0: combo.addItem(path,path)
                combo.setCurrentIndex(combo.findData(path))
        choose.clicked.connect(browse)
        horizontal.addWidget(combo,1);horizontal.addWidget(choose)
        form.addRow(label,row)
        return combo
    def review(self):
        result={'object':self.object.currentData() or '',
            'preview':self.preview.currentData() or '', 'matlib':self.matlib.currentData() or '',
            'material':self.material.text().strip(),
            'maps':{old:c.currentData() for old,c in self.maps if c.currentData()}}
        for path in [result['preview'],result['matlib']]+list(result['maps'].values()):
            if path and not Path(path).is_file():
                raise ValueError('Selected local file is missing: '+path)
        return result

