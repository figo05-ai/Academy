"""Read-only asset recognition in an isolated Max Batch process."""
from pathlib import Path
import os,re,json,traceback,sys
from collections import defaultdict
import xml.etree.ElementTree as ET
import collection_store as store
IMAGE_EXT={'.jpg','.jpeg','.png','.tif','.tiff','.exr','.hdr','.bmp','.tga','.psd'}
PREVIEW_EXT={'.jpg','.jpeg','.png','.bmp','.tif','.tiff'}
CHANNELS={'albedo':'albedo','diffuse':'albedo','diff':'albedo','dif':'albedo','color':'albedo',
 'opacity':'opacity','alpha':'opacity','normal':'normal','nor':'normal','roughness':'roughness',
 'rough':'roughness','translucency':'translucency','specular':'specular','spec':'specular',
 'gloss':'gloss','glossiness':'gloss','displacement':'displacement','disp':'displacement','bump':'bump'}
def channel_key(name):
    stem=Path(name).stem.casefold()
    parts=re.split(r'[_ .-]+',stem)
    for i in range(len(parts)-1,-1,-1):
        if parts[i] in CHANNELS:
            return (''.join(parts[:i]),CHANNELS[parts[i]])
    return ('','')
def inside(path,roots):
    for root in roots:
        try:
            Path(path).resolve().relative_to(Path(root).resolve())
            return True
        except ValueError:
            pass
    return False
def files_in(roots,errors):
    seen=set()
    for root in roots:
        def onerror(exc): errors.append(str(exc))
        for directory,dirs,files in os.walk(root,followlinks=False,onerror=onerror):
            dirs[:]=[d for d in dirs if not Path(directory,d).is_symlink()]
            for name in files:
                p=Path(directory,name)
                k=store.identity(p)
                if k not in seen:
                    seen.add(k)
                    yield p
def map_match(old,images,roots):
    bn=old.replace('\\','/').rsplit('/',1)[-1]
    exact=list(dict.fromkeys(images.get(bn.casefold(),[])))
    if Path(old).is_file() and inside(old,roots):
        return dict(reference=old,candidates=exact,resolved=str(Path(old).resolve()),status='resolved',channel=channel_key(bn)[1])
    status='resolved' if len(exact)==1 else ('ambiguous' if exact else 'missing')
    candidates=exact
    if not candidates:
        key=channel_key(bn)
        if key[0] and key[1]:
            candidates=[p for name,paths in images.items() if channel_key(name)==key for p in paths]
            if candidates: status='pattern_requires_review'
    return dict(reference=old,candidates=candidates,resolved=exact[0] if len(exact)==1 else '',
                status=status,channel=channel_key(bn)[1])
def scan(config,metadata,progress=lambda *args:None):
    errors=[]
    roles=config['folders']
    roots=list(dict.fromkeys(p for paths in roles.values() for p in paths))
    allfiles=list(files_in(roots,errors))
    modelroots=roles.get('models') or roles.get('mixed',[])
    previewroots=roles.get('previews') or roles.get('mixed',[])+roles.get('models',[])
    maproots=roles.get('maps') or roots
    models=[p for p in allfiles if p.suffix.casefold()=='.max' and inside(p,modelroots)]
    if config.get('explicit_models'):
        models=[Path(x) for x in config['explicit_models']]
    if config.get('workflow')=='manual':
        allfiles=[x for x in allfiles if x.suffix.casefold()!='.xml']
    images=defaultdict(list)
    previews=defaultdict(list)
    for p in allfiles:
        suffix=p.suffix.casefold()
        if suffix in IMAGE_EXT and inside(p,maproots):
            images[p.name.casefold()].append(str(p.resolve()))
        if suffix in PREVIEW_EXT and inside(p,previewroots) and not channel_key(p.name)[1]:
            previews[p.stem.casefold()].append(str(p.resolve()))
    indexes=defaultdict(list)
    index_files=[]
    for idx in [p for p in allfiles if p.suffix.casefold()=='.xml']:
        try:
            root=ET.parse(idx).getroot()
            if root.tag!='library': continue
            index_files.append(str(idx))
            for it in root.findall('item'):
                model=(it.findtext('maxfile') or '').strip()
                if not model: continue
                target=(idx.parent/model.replace('\\','/')).resolve()
                obj=(it.findtext('object') or '').strip()
                preview=(it.findtext('bitmap') or '').strip().replace('\\','/')
                explicit=[idx.parent/preview,idx.parent/'thumbnails'/Path(preview).name] if preview else []
                # Only existing indexed links inside selected folders are eligible.
                # The exact index-relative bitmap is authoritative. Only fall back
                # to the thumbnail directory when that declared file is absent.
                matched=[]
                for candidate in explicit:
                    if candidate.is_file() and inside(candidate,roots):
                        matched=[str(candidate.resolve())]
                        break
                if not matched and preview:
                    matched=previews.get(Path(preview).stem.casefold(),[])
                lib=(it.findtext('matlib') or '').strip()
                if config.get('renderer')=='corona':
                    lib=re.sub(r'\$\([^)]+\)','corona',lib)
                elif '$(' in lib:
                    lib=''
                libpath=(idx.parent/lib.replace('\\','/')).resolve() if lib else None
                indexes[store.identity(target)].append(dict(
                    name=(it.findtext('name') or obj).strip(),object=obj,
                    image=matched[0] if len(matched)==1 else '',
                    preview_candidates=matched,
                    matlib=str(libpath) if libpath and libpath.is_file() and inside(libpath,roots) else '',
                    material=(it.findtext('material') or '').strip(),index=str(idx)))
        except Exception as exc:
            errors.append('Index %s: %s'%(idx,exc))
    entries=[]
    for number,p in enumerate(models,1):
        progress(number,len(models),str(p))
        entry=dict(path=str(p.resolve()),name=p.stem,label=p.stem.replace('_',' '),category=config.get('category','Plants' if config.get('library_type')=='maxtree' else 'General'),
            species=re.sub(r'[_ ]\d+(?:_\d+)?$','',p.stem).replace('_',' '),
            collection_root=modelroots[0],variants=[],image='',issues=[],object_names=[],
            map_matches=[],preview_candidates=[],material_libraries=[str(x) for x in allfiles if x.suffix.casefold()=='.mat'],
            bytes=p.stat().st_size)
        try:
            names,refs=metadata(str(p))
            entry['object_names']=names
            entry['map_references']=refs
            indexed=[v for v in indexes.get(store.identity(p),[]) if v['object'] in names]
            if indexed:
                # Avoid duplicate object links from repeated index declarations.
                unique={}
                for v in indexed: unique.setdefault(v['object'],v)
                entry['variants']=list(unique.values())
                pc=list(dict.fromkeys(v['image'] for v in indexed if v['image']))
                # A file can have several valid variant previews; each is linked explicitly.
                entry['image']=pc[0] if pc else ''
                entry['preview_candidates']=list(dict.fromkeys(x for v in indexed for x in v['preview_candidates']))
                entry['object_evidence']='Explicit index object links'
            elif len(names)==1 and not re.fullmatch(r'(?:box|sphere|plane|object|camera|light|dummy|group|teapot)\d*',names[0],re.I):
                entry['variants']=[dict(name=names[0],object=names[0],image='',matlib='',material='')]
                entry['object_evidence']='Single clearly named scene object'
            else:
                entry['issues'].append('Object review required: select objects or the full block at insertion.')
            if not entry['image']:
                exact=previews.get(p.stem.casefold(),[])
                if config.get('workflow')!='manual' and not exact and len(names)==1:
                    exact=previews.get(names[0].casefold(),[])
                if config.get('workflow')!='manual' and not exact:
                    fallback=[]
                    base=re.sub(r'_(?:MESH|GFX)_(?:CRN|CORONA|VRAY)$','',p.stem,flags=re.I)
                    for stem,paths in previews.items():
                        if stem in {base.casefold()+'_preview',base.casefold()+'_thumb',base.casefold()+'_prv1',base.replace(' ','-').casefold()+'_prv1'}:
                            fallback+=paths
                    exact=list(dict.fromkeys(fallback))
                entry['preview_candidates']=exact
                if len(exact)==1:
                    entry['image']=exact[0]
                else:
                    entry['issues'].append('Missing preview' if not exact else 'Ambiguous preview: review candidates')
            for v in entry['variants']:
                if not v['image']: v['image']=entry['image']
            for ref in refs:
                old=ref['filename']
                suffix=Path(old.replace('\\','/')).suffix.casefold()
                if suffix in IMAGE_EXT:
                    match=map_match(old,images,maproots)
                    entry['map_matches'].append(match)
                    if match['status']!='resolved':
                        entry['issues'].append(match['status']+': '+old)
            evidence=bool(re.search(r'corona|_CRN',str(p),re.I) or any('corona' in v.get('matlib','').casefold() for v in indexed))
            entry['renderer_evidence']='Corona path/material index' if evidence else 'Not verified by scan; actual materials validated on insertion'
            if config.get('renderer')=='corona' and not evidence:
                entry['issues'].append('Renderer not verified by scan; Corona is required on insertion.')
            if not refs:
                entry['issues'].append('No asset references returned; maps will be checked on insertion.')
        except Exception:
            entry['issues'].append('Metadata read failed: '+traceback.format_exc())
        if config.get('workflow')=='structured':
            text=str(p).casefold()
            other=bool(re.search(r'vray|v-ray|_vr_|_vray|standard',text))
            corona=(entry.get('renderer_evidence')=='Corona path/material index' and not other)
            policy=config.get('renderer','corona')
            if policy=='corona' and not corona:
                entry['excluded']='Corona variant not verified' if not other else 'Other renderer variant'
            elif policy=='vray' and (not other or 'standard' in text or 'corona' in text):
                entry['excluded']='V-Ray variant not verified'
            elif policy=='source' and not entry.get('variants'):
                entry['issues'].append('Unsupported object structure: use manual review.')
        entries.append(entry)
    return dict(schema=1,collection_id=config['id'],complete=True,entries=entries,
        images=dict(images),indexes=index_files,errors=errors,
        metadata_files=[str(p) for p in allfiles if p.suffix.casefold() in ('.xml','.json','.mat')],
        counts={'models':len(models),'preview_files':sum(map(len,previews.values())),
                'map_image_files':sum(map(len,images.values())),'material_libraries':sum(p.suffix.casefold()=='.mat' for p in allfiles)})
def run(job_path):
    job=store.read_json(job_path)
    folder=Path(job_path).parent
    try:
        from pymxs import runtime as rt
        if rt.objects.count or str(rt.maxFileName):
            raise RuntimeError('Scanner requires its own empty Max batch process.')
        def metadata(path):
            names=[str(x) for x in rt.getMAXFileObjectNames(path)]
            refs=[dict(filename=str(a.filename),type=str(a.type)) for a in rt.getMAXFileAssetMetadata(path)]
            return names,refs
        def progress(number,total,path):
            if (folder/'cancel.json').exists():
                raise RuntimeError('Scan cancelled; prior catalog retained.')
            store.atomic_json(folder/'progress.json',dict(processed=number,total=total,path=path))
        result=scan(job['collection'],metadata,progress)
        store.atomic_json(folder/'result.json',result)
        store.atomic_json(folder/'progress.json',dict(complete=True,processed=len(result['entries']),total=len(result['entries'])))
    except Exception:
        store.atomic_json(folder/'error.json',dict(error=traceback.format_exc()))

