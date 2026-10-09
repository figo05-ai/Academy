from pathlib import Path
import json,re,collections
OUT=Path(__file__).parent
data=json.loads((OUT/'scan_candidates.json').read_text(encoding='utf-8'))
meta={m['path'].casefold():m for m in json.loads((OUT/'model_metadata.json').read_text(encoding='utf-8'))}
accepted=[];review=list(data['excluded']);seen=set()
def norm(s): return re.sub(r'[^a-z0-9]','',s.casefold())
for e in data['entries']:
    m=meta[e['path'].casefold()]
    names=m.get('objects',[])
    variants=[]
    for v in e['variants']:
        if v['object'] in names and all(old['object']!=v['object'] for old in variants):
            variants.append(v)
        elif v['object'] not in names:
            review.append(dict(collection=e['collection'],path=e['path'],reason='Indexed object absent: '+v['object']))
    if not e['variants']:
        base=re.sub(r'_(MESH|GFX)_CRN$','',e['name'],flags=re.I)
        for name in names:
            if len(names)==1 or norm(name).startswith(norm(base)):
                matches=data['collections'][e['collection']]['images'].get((name+'.jpg').casefold(),[])
                image=sorted(matches,key=len)[0] if matches else e['image']
                variants.append(dict(name=name,object=name,image=image,material='',matlib=''))
    if not variants:
        review.append(dict(collection=e['collection'],path=e['path'],reason='No safe indexed or matching plant object; '+str(names[:8])))
        continue
    if not e['image']:
        e['image']=next((v['image'] for v in variants if v.get('image')),'')
    if not e['image']:
        review.append(dict(collection=e['collection'],path=e['path'],reason='No established model/preview relationship'))
        continue
    e['variants']=variants
    # A catalog entry identifies a full model path; plant variants remain children.
    key=str(Path(e['path']).resolve()).casefold()
    if key in seen: continue
    seen.add(key)
    label=re.sub(r'^MT_PM_(?:V\d+_)?','',e['name'])
    e['label']=label.replace('_',' ')
    e['species']=re.sub(r'[\s_]*(?:\d+[_ ]?)+(?:[HL])?$','',label).replace('_',' ')
    e['map_references']=m.get('assets',[])
    missing=[]
    for a in m.get('assets',[]):
        bn=a['filename'].replace('\\','/').rsplit('/',1)[-1].casefold()
        if a['type'].casefold() in ('#bitmap','bitmap') and bn not in data['collections'][e['collection']]['images']:
            missing.append(a['filename'])
    e['metadata_missing_maps']=missing
    if missing:
        review.append(dict(collection=e['collection'],path=e['path'],reason='Metadata references not found in collection',maps=missing))
    if missing and e['collection']=='MaxtreePlantModelsVol15':
        review.append(dict(collection=e['collection'],path=e['path'],reason='HELD OUT: missing plant material texture confirmed by isolated insertion test',maps=missing))
        continue
    accepted.append(e)
assert sum(e['collection']=='MT Plants model vol.67' for e in accepted)==72
summary=[]
for col in data['summary']:
    rows=[e for e in accepted if e['collection']==col['collection']]
    col=dict(col,accepted_models=len(rows),plant_variants=sum(len(e['variants']) for e in rows),
        accepted_previews=sum(bool(e['image']) for e in rows),
        metadata_map_warnings=sum(bool(e['metadata_missing_maps']) for e in rows))
    summary.append(col)
result={'schema':2,'title':'مكتبة نباتات المكتب الفني','entries':accepted,'collections':data['collections']}
(OUT/'catalog.json').write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8')
(OUT/'scan_report.json').write_text(json.dumps({'summary':summary,'review':review},ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(summary,indent=2))

