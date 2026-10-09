from pathlib import Path
import json,os,traceback,builtins,time
from PySide6 import QtCore,QtWidgets,QtGui
from pymxs import runtime as rt
ROOT=Path(r'E:\max lib\Maxtree_Library_Tool')
OUT=Path(os.environ['OFFICE_BLOCK_TEST_SETTINGS']).parent
OUT.mkdir(parents=True,exist_ok=True)
def write(name,data):
    (OUT/name).write_text(json.dumps(data,ensure_ascii=False,indent=2,default=str),encoding='utf-8')
def start():
    try:
        assert rt.objects.count==0 and not str(rt.maxFileName),'Test session is not empty'
        rt.fileIn(str(ROOT/'Launch.ms'))
        w=builtins._maxtree_browser;w.show();w.raise_()
        import collection_store,asset_store
        write('ready.json',dict(pid=os.getpid(),title=w.windowTitle(),visible=w.isVisible(),logo=not w.logo.pixmap().isNull(),settings=str(collection_store.SETTINGS),asset_settings=str(asset_store.CONFIG),entries=len(w.entries),objects=int(rt.objects.count),build=w.build_label.text()))
    except Exception:write('ready.json',dict(error=traceback.format_exc()))
def command_tick():
    path=OUT/'command.py'
    if path.exists():
        code=path.read_text(encoding='utf-8-sig');path.unlink()
        try:exec(compile(code,str(path),'exec'),globals());write('command_result.json',dict(ok=True))
        except Exception:write('command_result.json',dict(error=traceback.format_exc()))
host_timer=QtCore.QTimer();host_timer.setInterval(500);host_timer.timeout.connect(command_tick);host_timer.start();builtins._general_block_host=host_timer
QtCore.QTimer.singleShot(5000,start)
