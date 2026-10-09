"""Material changes are confined to the newly imported node's private material graph."""
from pathlib import Path
from PySide6 import QtWidgets
from pymxs import runtime as rt

def assign(node,entry,parent=None):
    path=entry.get('manual_material','')
    if node.material: node.material=rt.copy(node.material)
    if not path:return
    if Path(path).suffix.casefold()!='.mat':raise RuntimeError('Only native .MAT material libraries are supported.')
    if not Path(path).is_file():raise RuntimeError('Material library is missing: '+path)
    mats=list(rt.loadTempMaterialLibrary(str(path)))
    if not mats:raise RuntimeError('The material library contains no readable materials.')
    by_name={}
    for i,m in enumerate(mats):by_name.setdefault(str(m.name),[]).append(i)
    targets=[]
    def walk(m,parent_m=None,slot=None,trail='Root'):
        if m is None:return
        name=str(m.name);matches=by_name.get(name,[])
        if len(matches)==1:
            targets.append((m,parent_m,slot,trail,matches[0],True));return
        count=int(rt.getNumSubMtls(m))
        if count and not matches:
            targets.append((m,parent_m,slot,trail,None,False))
            for j in range(1,count+1):walk(rt.getSubMtl(m,j),m,j,trail+'/'+str(j))
        else:targets.append((m,parent_m,slot,trail,None,False))
    if node.material:walk(node.material)
    else:targets.append((None,None,None,'Root',None,False))
    unresolved=[t for t in targets if not t[5]]
    choices={}
    if unresolved:
        dialog=QtWidgets.QDialog(parent);dialog.setWindowTitle('Review material mapping');dialog.resize(780,420)
        layout=QtWidgets.QVBoxLayout(dialog)
        note=QtWidgets.QLabel('No unique exact-name match for these imported materials. Select an explicit assignment, or keep each embedded material. Only this imported object is affected.')
        note.setWordWrap(True);layout.addWidget(note)
        form=QtWidgets.QFormLayout();layout.addLayout(form);combos=[]
        for m,pm,slot,trail,index,exact in unresolved:
            combo=QtWidgets.QComboBox();combo.addItem('Keep embedded material',-1)
            for i,candidate in enumerate(mats):combo.addItem(str(candidate.name)+' [library slot '+str(i+1)+']',i)
            form.addRow(trail+': '+(str(m.name) if m else '(no material)'),combo);combos.append((trail,combo))
        buttons=QtWidgets.QDialogButtonBox(QtWidgets.QDialogButtonBox.Ok|QtWidgets.QDialogButtonBox.Cancel);layout.addWidget(buttons)
        buttons.accepted.connect(dialog.accept);buttons.rejected.connect(dialog.reject)
        if dialog.exec()!=QtWidgets.QDialog.Accepted:raise RuntimeError('Material review cancelled; insertion rolled back.')
        choices={trail:c.currentData() for trail,c in combos}
    applied=[]
    replaced=[]
    for m,pm,slot,trail,index,exact in targets:
        if any(trail.startswith(t+'/') for t in replaced):continue
        chosen=index if exact else choices.get(trail,-1)
        if chosen is None or chosen<0:continue
        replacement=rt.copy(mats[chosen])
        if pm is None:node.material=replacement
        else:rt.setSubMtl(pm,slot,replacement)
        replaced.append(trail)
        applied.append(dict(target=trail,source_name=str(m.name) if m else '',material=str(replacement.name),exact=exact))
    entry['_material_assignment']=applied
