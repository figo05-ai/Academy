from pathlib import Path
import json,traceback,os,builtins
from PySide6 import QtCore,QtWidgets
from pymxs import runtime as rt
ROOT=Path(r'E:\max lib\Maxtree_Library_Tool')
OUT=ROOT/'settings'/'ui_validation';OUT.mkdir(parents=True,exist_ok=True)
def write(name,data):
    (OUT/name).write_text(json.dumps(data,ensure_ascii=False,indent=2),encoding='utf-8')
def start():
    try:
        if rt.objects.count or str(rt.maxFileName):raise RuntimeError('Dedicated validation session is not empty')
        rt.fileIn(str(ROOT/'Launch.ms'))
        w=builtins._maxtree_browser
        w.show();w.raise_();w.activateWindow()
        write('ready.json',dict(pid=os.getpid(),visible=w.isVisible(),title=w.windowTitle(),build=w.build_label.text(),modules=w.property('modulePaths'),objects=int(rt.objects.count)))
    except Exception:write('ready.json',dict(error=traceback.format_exc()))
def tick():
    path=OUT/'command.py'
    if not path.exists():return
    source=path.read_text(encoding='utf-8-sig');path.unlink()
    try:
        exec(compile(source,str(path),'exec'),globals());write('command_result.json',dict(ok=True))
    except Exception:write('command_result.json',dict(error=traceback.format_exc()))
timer=QtCore.QTimer();timer.setInterval(500);timer.timeout.connect(tick);timer.start()
builtins._library_validation_timer=timer
QtCore.QTimer.singleShot(5000,start)
