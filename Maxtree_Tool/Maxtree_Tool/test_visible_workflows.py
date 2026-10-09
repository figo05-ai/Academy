import asset_ui,asset_store,collection_store,maxtree_library,hashlib,time,copy
from PySide6 import QtGui
w=builtins._maxtree_browser
catalog=collection_store.read_json(ROOT/'catalog.json')
samples=[e for e in catalog['entries'] if 'vol.67' in e['path'].lower() and 'Carica_papaya_01' in e['path']][:3]
assert len(samples)>=3
modelroot=str(Path(samples[0]['path']).parent)
maproot=r'E:\max lib\maxtree\MT Plants model vol.67\MT Plants model vol.67\Maxtree - Plant Models Vol. 67_Maps\Maxtree - Plant Models Vol. 67_Maps'
previewroot=str(Path(modelroot)/'thumbnails')
protected=[ROOT/'catalog.json',ROOT/'academy_logo.png',ROOT/'settings/user_collections.json']
for e in samples:protected.extend([Path(e['path']),Path(e['image']),Path(e['variants'][0]['matlib'])])
protected.extend(list(Path(maproot).glob('*'))[:2])
def fingerprint(p):return dict(hash=hashlib.sha256(p.read_bytes()).hexdigest(),size=p.stat().st_size,mtime=p.stat().st_mtime_ns)
baseline={str(p):fingerprint(p) for p in protected if p.is_file()}
initial_assets=asset_store.state()
report=dict(result='RUNNING',pid=os.getpid(),launcher=str(ROOT/'Launch.ms'),build=w.build_label.text(),steps=[],source_baseline=baseline)
write('workflow_results.json',report)
scenarios=['structured','bulk','pergola_first','pergola_second','single','edit','rescan','rename','remove_entry','remove_libraries']
state=dict(index=0,phase='begin',dialog_seen=set(),libraries={},start=time.monotonic(),reports=[])
manager=asset_ui.open_manager(w)
manager.move(130,100)
sentinel=None

def fail(message):
    report['result']='FAIL';report['error']=message;report['stage']=scenarios[state['index']];write('workflow_results.json',report);test_timer.stop()
def click_accept(d):
    bb=d.findChild(QtWidgets.QDialogButtonBox)
    (bb.button(QtWidgets.QDialogButtonBox.Ok) or bb.button(QtWidgets.QDialogButtonBox.Save)).click()
def screenshot_top(d,name):
    pos=d.mapToGlobal(QtCore.QPoint(0,0));d.screen().grabWindow(0,pos.x(),pos.y(),d.width(),min(d.height(),145)).save(str(OUT/name))
def choose(cid):
    manager.libraries.setCurrentIndex(manager.libraries.findData(cid));manager.search.clear()
def select_sample(number=0):
    manager.search.setText(Path(samples[number]['path']).stem)
    assert manager.list.count()==1,(manager.list.count(),samples[number]['path'])
    manager.list.setCurrentRow(0)
def verify_insert(manual=False):
    global sentinel
    e=manager.selected();assert e and e['variants'];before=list(rt.objects)
    if sentinel is None:
        sentinel=rt.box(name='Library_Test_Unrelated',length=1,width=1,height=1)
        sentinel.material=rt.StandardMaterial(name=samples[0]['variants'][0]['material'])
        sentinel.material.diffuse=rt.color(17,31,47)
        state['sentinel_handle']=int(rt.getHandleByAnim(sentinel.material))
    manager.insert.click()
    assert hasattr(manager,'last_insert'),manager.status.text()
    fresh=[n for n in rt.objects if n not in before and n!=sentinel]
    assert len(fresh)==1,(len(fresh),manager.status.text())
    n=fresh[0]
    assert int(rt.getHandleByAnim(sentinel.material))==state['sentinel_handle']
    assert str(sentinel.material.diffuse)==str(rt.color(17,31,47))
    files=[]
    for obj in maxtree_library.material_tree(n.material):
        if rt.isProperty(obj,'filename'):
            value=str(rt.getProperty(obj,'filename') or '')
            if value:assert Path(value).is_file(),value;files.append(value)
    assert files
    record=dict(model=e['path'],material=str(n.material.name),maps=len(files),unrelated_material_unchanged=True,assignments=manager.last_insert['material_assignments'])
    if manual:assert record['assignments'] and all(x['exact'] for x in record['assignments']),record
    report['steps'].append(dict(action='insert',**record));write('workflow_results.json',report)
    rt.delete(n)
    sentinel.material.name='Unrelated_validation_material'

def advance(detail):
    report['steps'].append(dict(action=scenarios[state['index']],result='PASS',detail=detail));write('workflow_results.json',report)
    state['index']+=1;state['phase']='begin';state['dialog_seen']=set();state['start']=time.monotonic()

def drive():
    try:
        if time.monotonic()-state['start']>420:raise RuntimeError('Stage timed out: '+state['phase'])
        if state['index']>=len(scenarios):
            assert all(fingerprint(Path(p))==v for p,v in baseline.items()),'Protected file changed'
            assert len(maxtree_library.catalog()[0])==963
            report['source_settings_unchanged']=True;report['result']='PASS';write('workflow_results.json',report);test_timer.stop();return
        stage=scenarios[state['index']]
        modal=QtWidgets.QApplication.activeModalWidget()
        if isinstance(modal,QtWidgets.QMessageBox):
            if stage in ('remove_entry','remove_libraries'):
                modal.button(QtWidgets.QMessageBox.Yes).click();return
            raise RuntimeError('UI message: '+modal.text())
        if isinstance(modal,asset_ui.CreateLibraryDialog):
            if id(modal) in state['dialog_seen']:return
            state['dialog_seen'].add(id(modal));assert modal.isVisible()
            modal.name.setText({'structured':'Validation Maxtree Plants','bulk':'Validation Custom Trees','pergola_first':'Pergolas'}[stage])
            if stage=='structured':
                modal.workflow.setCurrentIndex(0);modal.kind.setCurrentIndex(0);modal.root.setText(modelroot)
                # Root must include separately located maps for structured import.
                modal.root.setText(str(Path(maproot).parents[1]))
                screenshot_top(modal,'structured_dialog.png')
            else:
                modal.workflow.setCurrentIndex(1);modal.method.setCurrentIndex(1 if stage=='bulk' else 0)
                if stage=='bulk':
                    modal.models.setText(modelroot);modal.maps.setText(maproot);modal.previews.setText(previewroot)
                screenshot_top(modal,'manual_dialog.png')
            click_accept(modal);return
        if isinstance(modal,asset_ui.AssetDialog):
            if id(modal) in state['dialog_seen']:return
            state['dialog_seen'].add(id(modal));assert modal.isVisible()
            number=1 if stage=='pergola_second' else (2 if stage=='edit' else 0)
            sample=samples[number]
            modal.name.setText('Edited block' if stage=='edit' else 'Test block '+str(number+1))
            modal.model.setText(sample['path']);modal.maps.setText(maproot);modal.material.setText(sample['variants'][0]['matlib']);modal.preview.setText(sample['image'])
            screenshot_top(modal,'single_block_dialog.png');click_accept(modal);return
        if isinstance(modal,asset_ui.ScanReport):
            if id(modal) in state['dialog_seen']:return
            state['dialog_seen'].add(id(modal));assert modal.isVisible()
            if stage=='structured': assert asset_store.state()==initial_assets, 'Settings changed before scan confirmation'
            modal.cross.setChecked(True)
            selected=modal.selected();assert selected,'No confirmed models'
            if stage=='structured':
                assert all('Corona' in e['renderer_evidence'] and not e.get('excluded') for e in selected)
            if stage=='bulk':assert len(selected)==72 and all(e['image'] for e in selected)
            report['steps'].append(dict(action='visible_scan_report',stage=stage,summary=modal.summary,confirmed=len(selected)))
            write('workflow_results.json',report);screenshot_top(modal,'scan_report_'+stage+'.png');click_accept(modal);state['phase']='finish';return
        if state['phase']=='begin':
            state['phase']='waiting'
            if stage in ('structured','bulk','pergola_first'):
                if stage=='structured':next(b for b in w.findChildren(QtWidgets.QPushButton) if b.text()=='Create Library').click()
                else:manager.actions['Create Library'].click()
            elif stage=='pergola_second':choose(state['libraries']['pergola_first']);manager.actions['Add one block'].click()
            elif stage=='single':manager.actions['Add Single Plant'].click()
            elif stage=='edit':choose(state['libraries']['pergola_first']);manager.search.setText('Test block 1');manager.actions['Edit / Replace'].click()
            elif stage=='rescan':choose(asset_store.SINGLE);manager.actions['Rescan'].click()
            elif stage=='rename':
                choose(state['libraries']['pergola_first']);manager.actions['Rename'].click();state['phase']='finish'
            elif stage=='remove_entry':
                choose(state['libraries']['pergola_first']);manager.search.clear();manager.actions['Remove entry'].click();state['phase']='finish'
            elif stage=='remove_libraries':
                choose(state['libraries']['structured']);manager.actions['Remove library'].click();state['phase']='finish'
            return
        if isinstance(modal,QtWidgets.QInputDialog):
            modal.setTextValue('Pergolas Renamed');modal.accept();return
        if state['phase']=='finish' and manager.job is None and modal is None:
            assert manager.isVisible()
            if stage in ('structured','bulk','pergola_first'):
                state['libraries'][stage]=manager.cid()
                assert asset_store.get(manager.cid())['entries']
                if stage in ('structured','bulk'):select_sample();verify_insert()
                else:verify_insert(True)
            elif stage=='pergola_second':
                assert len(asset_store.get(manager.cid())['entries'])==2
                manager.search.setText('Test block 2');verify_insert(True)
            elif stage=='single':
                assert manager.cid()==asset_store.SINGLE and len(manager.filtered)==1
                verify_insert(True)
                assert len(asset_store.get(state['libraries']['pergola_first'])['entries'])==2
            elif stage=='edit':
                assert len(asset_store.get(manager.cid())['entries'])==2
                manager.search.setText('Edited block');assert manager.selected()['path']==samples[2]['path'];verify_insert(True)
            elif stage=='rescan':assert len(asset_store.get(asset_store.SINGLE)['entries'])==1
            elif stage=='rename':assert asset_store.get(state['libraries']['pergola_first'])['name']=='Pergolas Renamed'
            elif stage=='remove_entry':assert len(asset_store.get(state['libraries']['pergola_first'])['entries'])==1
            elif stage=='remove_libraries':
                assert asset_store.get(state['libraries']['structured'])['removed']
                # Archive only the remaining validation records; never delete source files.
                for key in ('bulk','pergola_first'):asset_store.update(state['libraries'][key],removed=True)
                for e in list(asset_store.get(asset_store.SINGLE)['entries']):asset_store.remove_entry(asset_store.SINGLE,e['id'])
                manager.refresh()
            advance(dict(collection=manager.cid(),visible=True))
    except Exception:fail(traceback.format_exc())
test_timer=QtCore.QTimer();test_timer.setInterval(700);test_timer.timeout.connect(drive);test_timer.start();builtins._asset_workflow_test_timer=test_timer

def modal_drive():
    if test_timer.isActive() and QtWidgets.QApplication.activeModalWidget() is not None:drive()
modal_timer=QtCore.QTimer();modal_timer.setInterval(600);modal_timer.timeout.connect(modal_drive);modal_timer.start();builtins._asset_modal_timer=modal_timer
