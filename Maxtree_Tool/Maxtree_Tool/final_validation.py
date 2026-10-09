import sys,json,traceback,hashlib
from pathlib import Path
OUT=Path(__file__).parent
sys.path.insert(0,str(OUT))
from pymxs import runtime as rt
from PySide6 import QtWidgets,QtCore
import maxtree_library as lib
r={}
try:
    assert rt.objects.count==0 and str(rt.maxFileName)==''
    w=lib.show()
    QtWidgets.QApplication.processEvents()
    assert len(w.entries)==963
    assert sum(len(e['variants']) for e in w.entries)==1204
    paths=[str(e['path']).casefold() for e in w.entries]
    assert len(paths)==len(set(paths))
    assert len([e for e in w.entries if e['collection']=='MT Plants model vol.67'])==72
    checked=set()
    for e in w.entries:
        assert e['path'].is_file()
        for v in e['variants']:
            image=v['image'] or e['image']
            if str(image) not in checked:
                assert not lib.preview_pixmap(image,QtCore.QSize(125,125)).isNull(),str(image)
                checked.add(str(image))
    assert rt.objects.count==0
    r.update(catalog_models=963,plant_variants=1204,unique_previews_decoded=len(checked),
             existing_72_preserved=True,duplicate_full_paths=0,browsing_scene_nodes=0,
             title=w.windowTitle(),logo_sha256=hashlib.sha256((OUT/'academy_logo.png').read_bytes()).hexdigest())
    r['collections']={}
    for collection in sorted({e['collection'] for e in w.entries}):
        w.collection.setCurrentText(collection)
        assert w.filtered and all(e['collection']==collection for e in w.filtered)
        r['collections'][collection]=len(w.filtered)
    w.collection.setCurrentText('Globe Plants Bundle 11')
    assert w.variant.count()>1
    w.variant.setCurrentIndex(1)
    assert not w.preview.pixmap().isNull()
    r['variant_preview']='PASS'
    w.collection.setCurrentText('MT Plants model vol.67')
    w.search.setText('Carica_papaya_01_03')
    assert len(w.filtered)==1
    # Exercise actual Insert button handler; surface errors in the report instead of a modal dialog.
    def fail_dialog(*args):
        raise RuntimeError(str(args[-1]))
    QtWidgets.QMessageBox.warning=fail_dialog
    w.insert_selected()
    assert rt.objects.count==1,w.status.text()
    r['insert_button']='PASS'
    rt.execute('max undo')
    assert rt.objects.count==0
    r['undo']='PASS'
    w.search.clear()
    w.collection.setCurrentText('All collections')
    QtWidgets.QApplication.processEvents()
    w.grab().save(str(OUT/'test_browser_collections.png'))
    w.close()
    r['result']='PASS'
except Exception:
    r['result']='FAIL'
    r['error']=traceback.format_exc()
finally:
    (OUT/'final_validation.json').write_text(json.dumps(r,ensure_ascii=False,indent=2),encoding='utf-8')

