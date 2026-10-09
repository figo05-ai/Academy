"""Integration tests; launch ONLY in a clean 3dsmaxbatch session."""
from pathlib import Path
import sys,json,time,traceback,hashlib
OUT=Path(__file__).parent
sys.path.insert(0,str(OUT))
from pymxs import runtime as rt
from PySide6 import QtWidgets,QtCore
import collection_store as store
import collection_scan as scanner
import maxtree_library as lib
from collection_ui import CollectionDialog,ReviewDialog
r={'test_collection_name':'مكتبة المكتب الفني','steps':{}}
created=None
def save(): (OUT/'test_custom_results.json').write_text(json.dumps(r,ensure_ascii=False,indent=2),encoding='utf-8')
def fingerprint(path):
    p=Path(path)
    return {'size':p.stat().st_size,'mtime':p.stat().st_mtime_ns,'sha256':hashlib.sha256(p.read_bytes()).hexdigest()}
try:
    assert rt.objects.count==0 and not str(rt.maxFileName)
    baseline=store.read_json(OUT/'catalog.json')
    model=next(e for e in baseline['entries'] if e['name']=='MT_PM_V67_Carica_papaya_01_03')
    root=Path(model['path']).parent
    maproot=next(Path(paths[0]).parent for bn,paths in baseline['collections'][model['collection']]['images'].items() if bn.endswith('_albedo.jpg'))
    protected=[OUT/'catalog.json',OUT/'academy_logo.png',Path(model['path']),root/'index.xml',
               Path(model['image']),maproot/'MT_PM_V67_Carica_papaya_Leaf_01_albedo.jpg']
    protected=[p for p in protected if p.is_file()]
    initial={str(p):fingerprint(p) for p in protected}
    w=lib.show()
    # Batch hosts can post close events while pumping their event queue.
    # Keep the test dialog alive until the test explicitly completes.
    w.closeEvent=lambda event:event.ignore()
    w.hide()
    # Do not pump Max Batch's application shutdown events inside this test.
    assert w.windowTitle()=='مكتبة نباتات المكتب الفني' and not w.logo.pixmap().isNull()
    assert len(w.entries)==963
    dialog=CollectionDialog(w)
    dialog.name.setText(r['test_collection_name'])
    roles={'models':[str(root)],'previews':[str(root/'thumbnails')],'maps':[str(maproot)],'mixed':[]}
    for role,paths in roles.items():
        for path in paths:
            item=QtWidgets.QListWidgetItem(path)
            item.setData(QtCore.Qt.UserRole,(role,path))
            dialog.folders.addItem(item)
    created=dialog.create()
    cid=created['id']
    w.refresh_catalog(cid)
    assert w.collection.currentText()==r['test_collection_name']
    r['steps']['create']='PASS'
    save()
    heartbeats=[0]
    timer=QtCore.QTimer()
    timer.setInterval(100)
    timer.timeout.connect(lambda:heartbeats.__setitem__(0,heartbeats[0]+1))
    timer.start()
    def wait_scan():
        started=time.monotonic()
        while w.scan_job is not None:
            job=w.scan_job
            job.process.waitForFinished(250)
            if not job.ended: job.poll()
            if time.monotonic()-started>600:
                w.scan_job.cancel()
                raise RuntimeError('Scan timed out')
        assert store.get_collection(cid).get('scan'),w.status.text()
    w.start_scan(cid)
    wait_scan()
    scan=store.read_json(store.get_collection(cid)['scan'])
    assert scan['complete'] and len(scan['entries'])==72
    assert rt.objects.count==0
    assert timer.isActive()
    r['steps']['async_scan']={'result':'PASS','models':72,'scan_mode':'separate asynchronous process','current_scene_objects':0}
    assert len(w.entries)==963,'Duplicate full paths added'
    assert len(w.filtered)==72
    assert all(e['image'] for e in scan['entries'])
    target=next(e for e in scan['entries'] if e['path']==model['path'])
    assert target['variants'] and target['map_matches']
    assert all(m['status']=='resolved' for m in target['map_matches']),target['issues']
    r['steps']['preview_map_matching']={'result':'PASS','map_references':len(target['map_matches'])}
    # Explicit choices persist across rescan, even when scan settings are unchanged.
    review={'object':target['variants'][0]['object'],'preview':target['image'],
            'maps':{m['reference']:m['resolved'] for m in target['map_matches']}}
    store.save_review(cid,target['path'],review)
    w.refresh_catalog(cid)
    before_scan=store.get_collection(cid)['scan']
    w.start_scan(cid)
    wait_scan()
    assert store.get_collection(cid)['scan']!=before_scan
    assert store.get_collection(cid)['reviews'][store.identity(target['path'])]==review
    assert len(w.entries)==963
    r['steps']['rescan_preserves_review']='PASS'
    renamed=r['test_collection_name']+' — اختبار إعادة التسمية'
    store.rename_collection(cid,renamed)
    w.refresh_catalog(cid)
    assert w.collection.currentText()==renamed and len(w.filtered)==72
    r['steps']['rename']='PASS'
    w.search.setText('Carica_papaya_01_03')
    assert len(w.filtered)==1
    current=w.grid.currentItem().data(QtCore.Qt.UserRole)
    assert current.get('custom') and current['review']==review
    rd=ReviewDialog(current,w)
    assert rd.object.currentData()==review['object']
    rd.close()
    r['steps']['review_dialog']='PASS'
    def no_modal(*args): raise RuntimeError(str(args[-1]))
    QtWidgets.QMessageBox.warning=no_modal
    w.insert_selected()
    assert rt.objects.count==1,w.status.text()
    node=rt.selection[0]
    files=[]
    for obj in lib.material_tree(node.material):
        if rt.isProperty(obj,'filename'):
            path=str(rt.getProperty(obj,'filename') or '')
            if path:
                assert Path(path).is_file(),path
                files.append(path)
    assert files
    inst,_,_=w.importer.insert(current,mode='Instance')
    cp,_,_=w.importer.insert(current,mode='Copy')
    assert rt.areNodesInstances(node,inst) and not rt.areNodesInstances(inst,cp)
    r['steps']['insert_copy_instance']={'result':'PASS','resolved_map_properties':len(files),'objects':int(rt.objects.count)}
    # Do not save screenshots containing source asset previews.
    # Matching ambiguity test uses only references, not new or copied assets.
    reference=target['map_matches'][0]['reference']
    chosen=target['map_matches'][0]['resolved']
    match=scanner.map_match(Path(reference.replace('\\','/')).name,
        {Path(reference.replace('\\','/')).name.casefold():[chosen,str(Path(chosen).with_name('different_candidate.jpg'))]},[str(maproot)])
    assert match['status']=='ambiguous' and not match['resolved']
    r['steps']['ambiguous_maps_not_autoselected']='PASS'
    # Metadata ambiguity uses real source files with a synthetic name list, no scene writes.
    small=dict(created,folders={'models':[str(root)],'previews':[str(root/'thumbnails')],'maps':[str(maproot)],'mixed':[]})
    ambiguous=scanner.scan(small,lambda path:(['PossiblePlantA','PossiblePlantB'],[]))
    assert all(not e['variants'] and any('Object review' in i for i in e['issues']) for e in ambiguous['entries'])
    r['steps']['ambiguous_objects_require_review']='PASS'
    store.remove_collection(cid)
    w.refresh_catalog()
    assert cid not in lib.catalog_data()['collections'] and len(w.entries)==963
    assert root.is_dir()
    r['steps']['remove_reference_only']='PASS'
    assert all(fingerprint(p)==value for p,value in initial.items()),'Protected source/config changed'
    r['steps']['source_catalog_logo_unchanged']='PASS'
    timer.stop()
    w.closeEvent=lambda event:event.accept()
    w.close()
    r['result']='PASS'
except Exception:
    r['result']='FAIL'
    r['error']=traceback.format_exc()
finally:
    if created:
        try: store.remove_collection(created['id'])
        except Exception: pass
    save()

