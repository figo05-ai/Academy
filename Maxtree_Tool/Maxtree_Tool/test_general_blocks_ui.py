from collection_ui import ReviewDialog
import time,copy,traceback
assert str(collection_store.SETTINGS).startswith(str(OUT))
assert int(rt.objects.count)==0
sentinel=rt.box(name='PREEXISTING_SENTINEL',length=1,width=1,height=1,pos=rt.Point3(100,200,0))
sentinel.material=rt.StandardMaterial(name='FixtureMaterial');sentinel.material.diffuse=rt.color(13,27,41)
original_handle=int(rt.getHandleByAnim(sentinel.material));original_transform=str(sentinel.transform)
result=dict(result='RUNNING',pid=os.getpid(),title=w.windowTitle(),logo=not w.logo.pixmap().isNull(),base_entries=base_count,steps=[])
steps=['structured','bulk','single','single_copy','single_instance','single_copy_again','multi_full','multi_instance','multi_one','missing','duplicate','review_maps','duplicate_reviewed','rename','move_duplicate','move','edit_paths','review_objects','remove_entry','remove_library','base_rename','bad_record']
ts=dict(index=0,phase='begin',libraries={},started=time.monotonic(),modal_ids=set(),nodes={})
def persist():write('general_results.json',result)
persist()
def ids():return {int(rt.getHandleByAnim(n)) for n in rt.objects}
def search(name):
    manager.search.setText(name);assert manager.list.count()==1,(name,manager.list.count());manager.list.setCurrentRow(0)
def select_library(cid):manager.refresh(cid);manager.category_filter.setCurrentIndex(0)
def ok(d):
    b=d.findChild(QtWidgets.QDialogButtonBox);(b.button(QtWidgets.QDialogButtonBox.Ok) or b.button(QtWidgets.QDialogButtonBox.Save)).click()
def context(widget,owner,action):
    pos=widget.visualItemRect(widget.currentItem()).center();widget.customContextMenuRequested.emit(pos)
    menu=owner._block_context_menu;assert menu.isVisible();selected=next(a for a in menu.actions() if a.text()==action);menu.hide();selected.trigger()
def next_step(detail=None):
    result['steps'].append(dict(action=steps[ts['index']],result='PASS',detail=detail));persist();ts['index']+=1;ts['phase']='begin';ts['modal_ids']=set();ts['started']=time.monotonic()
def fail():
    result['result']='FAIL';result['error']=traceback.format_exc();result['stage']=steps[ts['index']];persist();drive_timer.stop();modal_timer.stop()
def unchanged():
    assert rt.isValidNode(sentinel) and str(sentinel.transform)==original_transform
    assert int(rt.getHandleByAnim(sentinel.material))==original_handle
    assert str(sentinel.material.diffuse)==str(rt.color(13,27,41))
    assert all(fp(Path(p))==value for p,value in fixture_baseline.items())
    assert all(fp(Path(p))==value for p,value in production_baseline.items())
def drive():
    try:
        if result['result']!='RUNNING':return
        if ts['index']>=len(steps):
            unchanged();result['result']='PASS';result['source_and_production_settings_unchanged']=True;persist();drive_timer.stop();modal_timer.stop();return
        stage=steps[ts['index']]
        if time.monotonic()-ts['started']>480:raise RuntimeError('Timed out '+ts['phase'])
        d=QtWidgets.QApplication.activeModalWidget()
        if isinstance(d,QtWidgets.QMessageBox):
            if stage in ('missing','duplicate','move_duplicate'):
                ts['warning']=d.text();d.accept();return
            if stage in ('remove_entry','remove_library'):
                d.button(QtWidgets.QMessageBox.Yes).click();return
            raise RuntimeError(d.text())
        if isinstance(d,asset_ui.CreateLibraryDialog):
            if id(d) in ts['modal_ids']:return
            ts['modal_ids'].add(id(d));assert d.isVisible()
            d.name.setText('General validation '+stage);d.category.setText('Architecture')
            if stage=='structured':
                d.workflow.setCurrentIndex(0);d.kind.setCurrentIndex(d.kind.findData('generic'));d.renderer.setCurrentIndex(d.renderer.findData('source'));d.root.setText(str(FIX));d.maps.setText(str(FIX/'Maps'))
            else:
                d.workflow.setCurrentIndex(1);d.method.setCurrentIndex(1 if stage=='bulk' else 0)
                if stage=='bulk':d.models.setText(str(FIX/'Models'));d.maps.setText(str(FIX/'Maps'));d.previews.setText(str(FIX/'Previews'))
            ok(d);return
        if isinstance(d,asset_ui.AssetDialog):
            if id(d) in ts['modal_ids']:return
            ts['modal_ids'].add(id(d))
            name='MultiBlock' if stage=='edit_paths' else 'SingleBlock'
            d.name.setText('Edited group reference' if stage=='edit_paths' else 'Single manual block');d.category.setText('Architecture')
            d.model.setText(fixtures[name]['path']);d.maps.setText(str(FIX/'Maps'));d.preview.setText(fixtures[name]['preview']);d.material.clear();ok(d);return
        if isinstance(d,asset_ui.ScanReport):
            if id(d) in ts['modal_ids']:return
            ts['modal_ids'].add(id(d));assert d.isVisible();d.cross.setChecked(True)
            expected=1 if stage=='single' else 5
            assert len(d.selected())==expected,(len(d.selected()),d.summary)
            assert d.summary['previews']==expected
            result['steps'].append(dict(action='visible_scan_report',stage=stage,summary=d.summary));persist();ts['phase']='finish';ok(d);return
        if isinstance(d,block_actions.ObjectDialog):
            if stage in ('multi_full','multi_instance'):d.mode.setCurrentIndex(1)
            else:
                d.mode.setCurrentIndex(0)
                for i in range(d.objects.count()):d.objects.item(i).setCheckState(QtCore.Qt.Checked if d.objects.item(i).text()=='Part_A' else QtCore.Qt.Unchecked)
            assert d.isVisible();ok(d);return
        if isinstance(d,ReviewDialog):
            if stage=='review_maps':
                assert d.maps
                for old,combo in d.maps:
                    if old.endswith('Shared.png'):combo.setCurrentIndex(combo.findData(str(FIX/'Maps/A/Shared.png')));assert combo.currentIndex()>0
            elif stage=='review_objects':d.object.setCurrentIndex(d.object.findData('Part_A'));assert d.object.currentIndex()>0
            ok(d);return
        if isinstance(d,QtWidgets.QInputDialog):d.setTextValue('Renamed block reference' if stage=='rename' else 'Preserved plant alias');d.accept();return
        if d and d.windowTitle()=='Move to Library':
            combo=d.findChild(QtWidgets.QComboBox);dest=('managed',ts['libraries']['single']);index=next(i for i in range(combo.count()) if tuple(combo.itemData(i))==dest);combo.setCurrentIndex(index);ok(d);return
        if ts['phase']=='begin':
            ts['phase']='waiting';ts.pop('warning',None)
            if stage in ('structured','bulk','single'):manager.actions['Create Library'].click();return
            if stage in ('single_copy','single_instance','single_copy_again','multi_full','multi_instance','multi_one','missing','duplicate','duplicate_reviewed'):
                select_library(ts['libraries']['bulk']);name='MultiBlock' if stage.startswith('multi') else ('MissingBlock' if stage=='missing' else ('DuplicateBlock' if stage.startswith('duplicate') else 'SingleBlock'));search(name)
                manager.mode.setCurrentText('Instance' if stage in ('single_instance','multi_instance') else 'Copy')
                manager.placement.setCurrentIndex(1 if stage=='single_copy_again' else 0)
                if stage=='single_copy_again':rt.select(sentinel)
                ts['before']=ids();ts['paths']=[str(rt.mapPaths.get(i)) for i in range(1,int(rt.mapPaths.count())+1)]
                manager.insert.click();ts['phase']='finish';return
            if stage=='review_maps':select_library(ts['libraries']['bulk']);search('DuplicateBlock');context(manager.list,manager,'Review Matches');ts['phase']='finish';return
            if stage=='rename':select_library(ts['libraries']['bulk']);search('SingleBlock');context(manager.list,manager,'Rename');ts['phase']='finish';return
            if stage in ('move_duplicate','move'):
                select_library(ts['libraries']['bulk']);search('Renamed block reference' if stage=='move_duplicate' else 'BareBlock');context(manager.list,manager,'Move to Library');ts['phase']='finish';return
            if stage=='edit_paths':select_library(ts['libraries']['single']);search('BareBlock');context(manager.list,manager,'Edit Paths');ts['phase']='finish';return
            if stage=='review_objects':search('Edited group reference');context(manager.list,manager,'Review Matches');ts['phase']='finish';return
            if stage=='remove_entry':search('Edited group reference');context(manager.list,manager,'Remove from Library');ts['phase']='finish';return
            if stage=='remove_library':select_library(ts['libraries']['single']);manager.actions['Remove library'].click();ts['phase']='finish';return
            if stage=='base_rename':
                manager.hide();w.show();w.search.clear();w.grid.setCurrentRow(0);ts['base_path']=str(w.grid.currentItem().data(QtCore.Qt.UserRole)['path']);context(w.grid,w,'Rename');ts['phase']='finish';return
            if stage=='bad_record':
                data=asset_store.get(ts['libraries']['bulk']);asset_store.update(data['id'],entries=data['entries']+[dict(label='Invalid test record')]);manager.show();select_library(data['id']);assert len(manager.filtered)==4;assert 'invalid' in manager.status.text().casefold();ts['phase']='finish';return
        if ts['phase']=='finish' and not d and manager.job is None:
            if stage in ('structured','bulk','single'):
                ts['libraries'][stage]=manager.cid();assert manager.preview.pixmap() and not manager.preview.pixmap().isNull()
                next_step(dict(library=manager.cid(),entries=len(manager.filtered)));return
            if stage in ('missing','duplicate'):
                assert ids()==ts['before'];assert ('missing' if stage=='missing' else 'ambiguous') in ts['warning'].casefold(),ts['warning']
                assert manager.selected()['_last_map_report'][0]['status']==('missing' if stage=='missing' else 'ambiguous')
            elif stage in ('single_copy','single_instance','single_copy_again','multi_full','multi_instance','multi_one','duplicate_reviewed'):
                fresh=[n for n in rt.objects if int(rt.getHandleByAnim(n)) not in ts['before']];assert fresh,manager.status.text()
                geometry=[n for n in fresh if str(rt.superClassOf(n)).casefold()=='geometryclass']
                assert len(geometry)==(2 if stage in ('multi_full','multi_instance') else 1),(stage,len(geometry))
                ts['nodes'][stage]=fresh
                if stage=='single_instance':assert rt.areNodesInstances(geometry[0],ts['nodes']['single_copy'][0])
                if stage=='single_copy_again':assert not rt.areNodesInstances(geometry[0],ts['nodes']['single_copy'][0]);assert list(geometry[0].position)==list(sentinel.position)
                if stage in ('multi_full','multi_instance'):
                    assert any(rt.isGroupHead(n) for n in fresh)
                    assert abs(abs(geometry[1].position.z-geometry[0].position.z)-16)<0.001
                if stage=='multi_instance':
                    originals=[n for n in ts['nodes']['multi_full'] if str(rt.superClassOf(n)).casefold()=='geometryclass']
                    assert all(any(rt.areNodesInstances(n,o) for o in originals) for n in geometry)
                assert manager.last_insert['summary']['missing']==manager.last_insert['summary']['ambiguous']==0
            elif stage=='review_maps':assert asset_store.get(ts['libraries']['bulk'])['entries'][next(i for i,e in enumerate(asset_store.get(ts['libraries']['bulk'])['entries']) if Path(e['path']).stem=='DuplicateBlock')]['review']['maps']
            elif stage=='rename':search('Renamed block reference');assert Path(manager.selected()['path']).name=='SingleBlock.max'
            elif stage=='move_duplicate':assert 'already exists' in ts['warning'];assert len(asset_store.get(ts['libraries']['bulk'])['entries'])==5
            elif stage=='move':assert len(asset_store.get(ts['libraries']['bulk'])['entries'])==4 and len(asset_store.get(ts['libraries']['single'])['entries'])==2
            elif stage=='edit_paths':search('Edited group reference');assert manager.selected()['path']==fixtures['MultiBlock']['path']
            elif stage=='review_objects':search('Edited group reference');assert manager.selected()['review']['object']=='Part_A'
            elif stage=='remove_entry':assert len(asset_store.get(ts['libraries']['single'])['entries'])==1
            elif stage=='remove_library':assert asset_store.get(ts['libraries']['single'])['removed']
            elif stage=='base_rename':
                w.search.setText('Preserved plant alias');assert w.grid.count()==1;assert str(w.grid.currentItem().data(QtCore.Qt.UserRole)['path'])==ts['base_path'];assert len(w.entries)==base_count
            if stage in ('single_copy','single_instance','single_copy_again','multi_full','multi_instance','multi_one','missing','duplicate','duplicate_reviewed'):
                now=[str(rt.mapPaths.get(i)) for i in range(1,int(rt.mapPaths.count())+1)];assert now==ts['paths']
            unchanged();next_step(dict(scene_objects=int(rt.objects.count),unrelated_unchanged=True))
    except Exception:fail()
drive_timer=QtCore.QTimer();drive_timer.setInterval(700);drive_timer.timeout.connect(drive)
modal_timer=QtCore.QTimer();modal_timer.setInterval(550);modal_timer.timeout.connect(lambda:drive() if QtWidgets.QApplication.activeModalWidget() is not None else None)
drive_timer.start();modal_timer.start();builtins._general_tests=(drive_timer,modal_timer)
