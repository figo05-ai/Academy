"""Shared block selection, context menus and review actions."""
from pathlib import Path
from PySide6 import QtCore,QtWidgets
from pymxs import runtime as rt
import block_catalog

class ObjectDialog(QtWidgets.QDialog):
    def __init__(self,names,entry,variant_index=0,parent=None):
        super().__init__(parent);self.setWindowTitle('Choose objects for this block');self.resize(640,480)
        self.names=names;layout=QtWidgets.QVBoxLayout(self)
        label=QtWidgets.QLabel('This MAX file contains multiple objects. Select specific objects, or explicitly choose the complete block. Cameras, lights and helpers are included only when selected.');label.setWordWrap(True);layout.addWidget(label)
        self.mode=QtWidgets.QComboBox();self.mode.addItems(['Selected objects only','Full block / all objects (explicit)']);layout.addWidget(self.mode)
        self.objects=QtWidgets.QListWidget();layout.addWidget(self.objects)
        review=entry.get('review',{});selected=review.get('objects',[])
        if not selected and review.get('object'):selected=[review['object']]
        if not selected and entry.get('variants'):
            selected=[entry['variants'][max(0,min(variant_index,len(entry['variants'])-1))]['object']]
        for name in names:
            item=QtWidgets.QListWidgetItem(name);item.setFlags(item.flags()|QtCore.Qt.ItemIsUserCheckable);item.setCheckState(QtCore.Qt.Checked if name in selected else QtCore.Qt.Unchecked);self.objects.addItem(item)
        if review.get('full_block'):self.mode.setCurrentIndex(1)
        self.mode.currentIndexChanged.connect(lambda i:self.objects.setEnabled(i==0))
        buttons=QtWidgets.QDialogButtonBox(QtWidgets.QDialogButtonBox.Ok|QtWidgets.QDialogButtonBox.Cancel);layout.addWidget(buttons)
        buttons.accepted.connect(self.validate);buttons.rejected.connect(self.reject)
    def validate(self):
        if not self.choice()[0]:QtWidgets.QMessageBox.warning(self,'Choose objects','Select at least one object.');return
        self.accept()
    def choice(self):
        full=self.mode.currentIndex()==1
        return (list(self.names) if full else [self.objects.item(i).text() for i in range(self.objects.count()) if self.objects.item(i).checkState()==QtCore.Qt.Checked]),full

def maps_text(entry):
    from block_runtime import summary_text
    records=entry.get('_last_map_report') or entry.get('map_matches',[])
    return summary_text(records) if records else 'Maps: not yet checked in the imported material graph'

def details(entry,library=''):
    lines=[entry.get('display_name') or entry.get('label',''), 'Category: '+entry.get('category','General'),library,'MAX: '+Path(entry['path']).name,str(entry['path']),maps_text(entry)]
    for key,label in [('path','MAX file'),('image','Preview'),('maps_folder','Maps folder')]:
        if entry.get(key) and not Path(entry[key]).exists():lines.append(label+' is missing: '+str(entry[key]))
    return '\n'.join(lines+entry.get('issues',[])[:4])

def attach(widget,owner,get_entry,refresh):
    widget.setContextMenuPolicy(QtCore.Qt.CustomContextMenu)
    def menu_at(pos):
        item=widget.itemAt(pos)
        if not item:return
        widget.setCurrentItem(item);entry=get_entry()
        if not entry:return
        menu=QtWidgets.QMenu(widget)
        for title in ('Rename','Move to Library','Edit Paths','Review Matches','Remove from Library'):
            action=menu.addAction(title);action.triggered.connect(lambda checked=False,t=title:run(owner,t,get_entry(),refresh))
        owner._block_context_menu=menu;menu.popup(widget.viewport().mapToGlobal(pos))
    widget.customContextMenuRequested.connect(menu_at)

def run(owner,action,entry,refresh):
    if not entry:return
    import maxtree_license_manager
    if not maxtree_license_manager.get_manager().is_authorized():
        QtWidgets.QMessageBox.warning(owner,'Access Locked','Access locked. Log in with an active Maxtree license to perform catalog operations.')
        return
    try:
        if action=='Rename':
            value,ok=QtWidgets.QInputDialog.getText(owner,'Rename block','Display name',text=entry.get('display_name') or entry.get('label',''))
            if not ok:return
            if not value.strip():raise ValueError('Enter a display name.')
            block_catalog.edit(entry,dict(display_name=value.strip()))
        elif action=='Move to Library':
            dialog=QtWidgets.QDialog(owner);dialog.setWindowTitle('Move to Library');layout=QtWidgets.QVBoxLayout(dialog);combo=QtWidgets.QComboBox();layout.addWidget(combo)
            for label,data in block_catalog.destinations():
                if data!=(entry.get('_ref_kind','managed'),entry['collection']):combo.addItem(label,data)
            label=QtWidgets.QLabel('Move the reference only. A duplicate MAX path in the destination prevents the move.');label.setWordWrap(True);layout.addWidget(label)
            bb=QtWidgets.QDialogButtonBox(QtWidgets.QDialogButtonBox.Ok|QtWidgets.QDialogButtonBox.Cancel);layout.addWidget(bb);bb.accepted.connect(dialog.accept);bb.rejected.connect(dialog.reject)
            if dialog.exec()!=QtWidgets.QDialog.Accepted:return
            if not combo.currentData():raise ValueError('Create a destination library first.')
            block_catalog.move(entry,*combo.currentData())
        elif action=='Edit Paths':
            from asset_ui import AssetDialog
            d=AssetDialog(owner,entry)
            if d.exec()!=QtWidgets.QDialog.Accepted:return
            values=d.values();changed=str(values['path'])!=str(entry['path'])
            if changed:
                values.update(object_names=[],variants=[],review={},map_matches=[],issues=['MAX path changed. Choose objects at insertion or use Review Matches.'])
            values['image']=values.get('explicit_preview','');block_catalog.edit(entry,values)
        elif action=='Review Matches':
            from collection_ui import ReviewDialog
            if not Path(entry['path']).is_file():raise ValueError('MAX file is missing; use Edit Paths first.')
            entry['object_names']=[str(x) for x in rt.getMAXFileObjectNames(str(entry['path']))]
            if entry.get('_last_map_report'):entry['map_matches']=entry['_last_map_report']
            d=ReviewDialog(entry,owner)
            if d.exec()!=QtWidgets.QDialog.Accepted:return
            block_catalog.edit(entry,dict(review=d.review(),object_names=entry['object_names']))
        elif action=='Remove from Library':
            if QtWidgets.QMessageBox.question(owner,'Remove reference','Remove this library reference only? No source files will be deleted.')!=QtWidgets.QMessageBox.Yes:return
            block_catalog.remove(entry)
        import maxtree_library,builtins
        maxtree_library.catalog_data.cache_clear()
        refresh()
        # Keep the edited reference selected after rebuilding the visible list.
        if action in ('Rename','Edit Paths','Review Matches') and hasattr(owner,'filtered'):
            wanted=entry.get('id') if entry.get('_ref_kind')!='base' else entry.get('_reference_key')
            field='id' if entry.get('_ref_kind')!='base' else '_reference_key'
            for index,candidate in enumerate(owner.filtered):
                if wanted and candidate.get(field)==wanted:
                    owner.page=index//12;owner.populate()
                    view=owner.list if hasattr(owner,'list') else owner.grid
                    view.setCurrentRow(index%12);break
        browser=getattr(builtins,'_maxtree_browser',None)
        if browser and browser is not owner:browser.refresh_catalog(browser.collection.currentData())
        elif browser and getattr(browser,'asset_manager',None):browser.asset_manager.refresh()
    except Exception as exc:QtWidgets.QMessageBox.warning(owner,'Block library',str(exc))
