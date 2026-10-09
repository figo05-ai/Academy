"""Read-only source inventory. Outputs only beside this script."""
from pathlib import Path
import json, re, collections
import xml.etree.ElementTree as ET
ROOT=Path(r'E:\max lib\maxtree')
OUT=Path(__file__).parent
COLLECTIONS=['Bundle 36 Indian Plants','Globe Plants Bundle 11','Maxtree - Plant Models Vol. 77','maxtree_plant_models_112','Maxtree_PlantModels_Vol_004','MaxtreePlantModelsVol.7','MaxtreePlantModelsVol15','MaxtreePlantsModelsVol.08','MT Plants model vol.56','MT Plants model vol.67','MT Plants model vol.105']
def key(p): return str(p.resolve()).casefold()
def noncorona(p): return bool(re.search(r'vray|v-ray|mentalray|octane',str(p),re.I))
def resolve(base,s): return (base / s.replace('\\','/')).resolve()
def preview(base,s,images):
    if not s: return ''
    name=Path(s.replace('\\','/')).name
    preferred=[resolve(base,s),base/'thumbnails'/name]
    for p in preferred:
        if p.is_file(): return str(p)
    found=images.get(name.casefold(),[])
    return str(sorted(found,key=lambda p:len(str(p)))[0]) if found else ''
entries=[]; excluded=[]; summary=[]; collections_data={}
for name in COLLECTIONS:
    root=ROOT/name
    files=[p for p in root.rglob('*') if p.is_file()]
    models=[p for p in files if p.suffix.lower()=='.max']
    images=collections.defaultdict(list)
    for p in files:
        if p.suffix.lower() in ('.jpg','.jpeg','.png','.tif','.tiff','.exr','.hdr','.bmp','.tga','.psd'):
            images[p.name.casefold()].append(p)
    indexed=collections.defaultdict(list)
    for idx in [p for p in files if p.name.lower()=='index.xml' and not noncorona(p)]:
        tree=ET.parse(idx).getroot()
        for item in tree.findall('item'):
            d={k:(item.findtext(k) or '').strip() for k in ('name','maxfile','object','bitmap','material','matlib')}
            if not d['maxfile']: continue
            path=resolve(idx.parent,d['maxfile'])
            lib=re.sub(r'\$\([^)]+\)','corona',d['matlib'])
            libpath=resolve(idx.parent,lib) if lib else None
            indexed[key(path)].append(dict(name=d['name'],object=d['object'],
                image=preview(idx.parent,d['bitmap'],images),material=d['material'],
                matlib=str(libpath) if libpath and libpath.is_file() else '',
                index=str(idx)))
    selected=[]; reasons=collections.Counter(); verified_count=0
    for p in models:
        variants=indexed.get(key(p),[])
        corona=bool(re.search(r'corona|_CRN',str(p),re.I))
        if not corona and not noncorona(p) and variants and all(v['matlib'] for v in variants):
            corona=True
        if noncorona(p):
            reason='Other renderer variant'
        elif not corona:
            reason='Renderer unverified'
        elif re.search(r'GrowFX|[\\/]GFX[\\/]',str(p),re.I):
            verified_count+=1
            reason='Procedural GrowFX/GFX: dependency and variant relationships not yet verified'
        elif re.search(r'_Scene|_Sence',str(p),re.I):
            reason='Demonstration scene, not isolated plant asset'
        else:
            verified_count+=1
            if not variants:
                # Same explicit index model basename in another folder: verify object names later.
                same=[vs for k,vs in indexed.items() if Path(k).name==p.name.casefold()]
                if len(same)==1:
                    variants=[dict(v,matlib='',material='') for v in same[0]]
            image=preview(p.parent,p.stem+'.jpg',images)
            if not image:
                species=re.sub(r'_(MESH|GFX)_CRN$','',p.stem,flags=re.I)
                image=preview(p.parent,species.replace(' ','-')+'_PRV1.jpg',images)
            entry=dict(path=str(p.resolve()),collection=name,collection_root=str(root),
                name=p.stem,variants=variants,image=image or (variants[0]['image'] if variants else ''),
                evidence=('Corona/CRN model path' if re.search(r'corona|_CRN',str(p),re.I)
                          else 'Explicit index links to existing Corona material library'),
                bytes=p.stat().st_size)
            entries.append(entry);selected.append(entry)
            continue
        excluded.append(dict(path=str(p),collection=name,reason=reason))
        reasons[reason]+=1
    collections_data[name]={'images':{k:[str(p) for p in v] for k,v in images.items()},
        'materials':[str(p) for p in files if p.suffix.lower()=='.mat'],
        'root':str(root)}
    summary.append(dict(collection=name,total_max=len(models),corona_plant_files_found=verified_count,
        candidates=len(selected),matching_previews=sum(bool(e['image']) for e in selected),
        corona_matlibs=sum('corona' in p.name.lower() for p in files if p.suffix.lower()=='.mat'),
        image_files=sum(len(v) for v in images.values()),excluded=dict(reasons)))
data={'schema':2,'root':str(ROOT),'entries':entries,'collections':collections_data,'excluded':excluded,'summary':summary}
(OUT/'scan_candidates.json').write_text(json.dumps(data,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(summary,ensure_ascii=False,indent=2))

