"""Run ONLY through tests_collections.ms in a separate empty Max Batch session."""
import sys,json,traceback,time,hashlib
from pathlib import Path
OUT=Path(__file__).parent
sys.path.insert(0,str(OUT))
from pymxs import runtime as rt
from PySide6 import QtWidgets,QtCore
import maxtree_library as lib
report={'tests':[]}
def save():
    (OUT/'test_results_collections.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
try:
    assert rt.objects.count==0 and str(rt.maxFileName)=='', 'Requires an empty batch session'
    report['max_version']=str(rt.maxVersion())
    rt.fileIn(str(OUT/'Launch.ms'))
    import builtins
    w=builtins._maxtree_browser
    QtWidgets.QApplication.processEvents()
    assert w.windowTitle()==lib.TITLE
    assert not w.logo.pixmap().isNull()
    assert len({str(e['path']).casefold() for e in w.entries})==len(w.entries)
    assert sum(e['collection']=='MT Plants model vol.67' for e in w.entries)==72
    report['catalog_models']=len(w.entries)
    report['plant_variants']=sum(len(e['variants']) for e in w.entries)
    report['ui']={}
    for collection in sorted({e['collection'] for e in w.entries}):
        w.collection.setCurrentText(collection)
        assert w.filtered and all(e['collection']==collection for e in w.filtered)
        assert not w.preview.pixmap().isNull()
        e=w.filtered[0]
        w.search.setText(e['name'])
        assert any(x['path']==e['path'] for x in w.filtered)
        w.search.clear()
        report['ui'][collection]={'models':len(w.filtered),'filter_search_preview':'PASS'}
    assert rt.objects.count==0,'Browsing loaded scene objects'
    report['browsing_without_models']='PASS'
    w.collection.setCurrentText('All collections')
    w.grab().save(str(OUT/'test_browser_collections.png'))
    save()
    chosen={}
    for e in sorted(w.entries,key=lambda e:e['bytes']):
        route=(e['collection'],str(e['path'].parent))
        chosen.setdefault(route,e)
    for e in w.entries:
        if e['metadata_missing_maps']:
            chosen.setdefault(('missing',e['collection'],tuple(e['metadata_missing_maps'])),e)
    for e in chosen.values():
        rt.resetMaxFile(rt.Name('noPrompt'))
        importer=lib.Importer()
        item={'collection':e['collection'],'path':str(e['path']),'variant':e['variants'][0]['object']}
        started=time.monotonic()
        try:
            before=Path(e['path']).stat()
            n,fixed,warnings=importer.insert(e)
            assert rt.objects.count==1
            assert fixed and not warnings
            refs=[]
            for obj in lib.material_tree(n.material):
                if rt.isProperty(obj,'filename'):
                    file=str(rt.getProperty(obj,'filename') or '')
                    if file:
                        assert Path(file).is_file(),file
                        refs.append(file)
            assert refs
            instance,_,_=importer.insert(e,mode='Instance')
            copy,_,_=importer.insert(e,mode='Copy')
            assert rt.areNodesInstances(n,instance)
            assert not rt.areNodesInstances(instance,copy)
            after=Path(e['path']).stat()
            assert (before.st_size,before.st_mtime_ns)==(after.st_size,after.st_mtime_ns)
            item.update(result='PASS',maps=len(fixed),material=str(n.material.name),node_class=str(rt.classOf(n)))
        except Exception:
            item.update(result='FAIL',error=traceback.format_exc(),remaining_nodes=int(rt.objects.count))
        item['seconds']=round(time.monotonic()-started,2)
        report['tests'].append(item)
        save()
    rt.resetMaxFile(rt.Name('noPrompt'))
    # Error paths must not mutate a scene.
    importer=lib.Importer()
    e=dict(w.entries[0],path=Path('C:/__missing_plant_test__.max'))
    try:
        importer.insert(e)
        raise AssertionError('Missing MAX unexpectedly accepted')
    except RuntimeError as exc:
        assert 'not found' in str(exc)
    assert rt.objects.count==0
    report['missing_max_rollback']='PASS'
    w.close()
    report['complete']=True
except Exception:
    report['fatal']=traceback.format_exc()
finally:
    save()

