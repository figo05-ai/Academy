"""Deterministic entry point: always executes modules from this launcher folder."""
from pathlib import Path
import sys,types,builtins
ROOT=Path(__file__).resolve().parent

def launch():
    old=getattr(builtins,'_maxtree_browser',None)
    if old:
        for window in (old,getattr(old,'asset_manager',None)):
            if window and (getattr(window,'scan_job',None) or getattr(window,'job',None)):
                raise RuntimeError('Finish or cancel the running library scan before reloading.')
        try:old.close();old.deleteLater()
        except RuntimeError:pass
    names=['collection_store','collection_scan','collection_ui','asset_store','asset_materials','asset_ui','maxtree_library']
    sys.path[:]=[str(ROOT)]+[x for x in sys.path if str(x).casefold()!=str(ROOT).casefold()]
    # Execute exact source files, avoiding stale imported modules and bytecode.
    for name in names:
        path=ROOT/(name+'.py')
        module=types.ModuleType(name);module.__file__=str(path);module.__package__=''
        sys.modules[name]=module
        exec(compile(path.read_text(encoding='utf-8-sig'),str(path),'exec'),module.__dict__)
    window=sys.modules['maxtree_library'].show()
    window.setProperty('launcherPath',str(ROOT/'Launch.ms'))
    window.setProperty('modulePaths',{name:sys.modules[name].__file__ for name in names})
    return window

try:
    launch()
except Exception:
    import traceback
    from PySide6 import QtWidgets
    QtWidgets.QMessageBox.critical(None,'Plant library launch failed',traceback.format_exc())
