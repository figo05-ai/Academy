import hashlib,xml.etree.ElementTree as ET
import collection_store,asset_store,maxtree_library,asset_ui,block_runtime,block_actions,block_catalog
assert rt.objects.count==0 and not str(rt.maxFileName)
assert str(collection_store.SETTINGS).startswith(str(OUT))
FIX=OUT/'fixtures'
for d in ('Models','Maps','Maps/A','Maps/B','Previews'):(FIX/d).mkdir(parents=True,exist_ok=True)
assert not list((FIX/'Models').glob('*.max')),'Refuse to overwrite existing fixture MAX files'
def png(path,color):
    im=QtGui.QImage(24,24,QtGui.QImage.Format_RGB32);im.fill(QtGui.QColor(color));assert im.save(str(path))
png(FIX/'Maps/Paint.png','#5588bb');png(FIX/'Maps/A/Shared.png','#558844');png(FIX/'Maps/B/Shared.png','#bb5544')
fixtures={}
for name,texture,multi in [('SingleBlock','Paint.png',False),('MultiBlock','Paint.png',True),('MissingBlock','Absent.png',False),('DuplicateBlock','Shared.png',False),('BareBlock',None,False)]:
    rt.resetMaxFile(rt.Name('noPrompt'))
    a=rt.box(name='Part_A' if multi else name+'_Object',length=10,width=10,height=10,pos=rt.Point3(20,0,0))
    if texture:
        mat=rt.StandardMaterial(name='FixtureMaterial');mat.diffuseMap=rt.Bitmaptexture(filename=str(FIX/'OriginalLocation'/texture));a.material=mat
    if multi:
        b=rt.box(name='Part_B',length=6,width=6,height=6,pos=rt.Point3(20,0,16));b.material=mat
        rt.group(rt.Array(a,b),name='Fixture_Group')
    path=FIX/'Models'/(name+'.max');assert rt.saveMaxFile(str(path),quiet=True)
    png(FIX/'Previews'/(name+'.png'),'#667788')
    fixtures[name]=dict(path=str(path),preview=str(FIX/'Previews'/(name+'.png')),objects=[str(x) for x in rt.getMAXFileObjectNames(str(path))])
rt.resetMaxFile(rt.Name('noPrompt'))
root=ET.Element('library')
for name in ('SingleBlock','MultiBlock','BareBlock'):
    for obj in fixtures[name]['objects']:
        item=ET.SubElement(root,'item')
        for tag,value in [('name',name+' - '+obj),('maxfile','Models/'+name+'.max'),('object',obj),('bitmap','Previews/'+name+'.png')]:ET.SubElement(item,tag).text=value
ET.ElementTree(root).write(FIX/'index.xml',encoding='utf-8',xml_declaration=True)
def fp(path):
    path=Path(path);return dict(hash=hashlib.sha256(path.read_bytes()).hexdigest(),size=path.stat().st_size,mtime=path.stat().st_mtime_ns)
fixture_baseline={str(p):fp(p) for p in FIX.rglob('*') if p.is_file()}
production_names=['catalog.json','academy_logo.png','settings/user_collections.json','settings/asset_libraries.json']
production_baseline={str(ROOT/n):fp(ROOT/n) for n in production_names}
base_count=len(builtins._maxtree_browser.entries)
write('fixtures.json',dict(fixtures=fixtures,baseline=fixture_baseline,production=production_baseline,base_count=base_count))
# Verify the temporary map-search API before testing insertion.
before_paths=[str(rt.mapPaths.get(i)) for i in range(1,int(rt.mapPaths.count())+1)]
with block_runtime.temporary_search_paths([str(FIX/'Maps')]):
    inside_paths=[str(rt.mapPaths.get(i)) for i in range(1,int(rt.mapPaths.count())+1)]
after_paths=[str(rt.mapPaths.get(i)) for i in range(1,int(rt.mapPaths.count())+1)]
write('search_path_probe.json',dict(restored=before_paths==after_paths,temporary_added=str(FIX/'Maps') in inside_paths,objects=int(rt.objects.count)))
