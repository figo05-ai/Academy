"""Reference-only individual assets and explicitly populated libraries."""
from pathlib import Path
import uuid,copy,datetime
import collection_store as store
CONFIG=store.SETTINGS/'asset_libraries.json'
SINGLE='single_assets'

def state():
    if not CONFIG.exists(): return {'schema':1,'libraries':[]}
    result=store.read_json(CONFIG)
    if result.get('schema')!=1: raise ValueError('Unsupported asset library settings version')
    return result

def save(data):
    if CONFIG.exists():
        stamp=datetime.datetime.now().strftime('%Y%m%d_%H%M%S_%f')
        store.atomic_json(store.SETTINGS/'history'/('assets_'+stamp+'.json'),state())
    store.atomic_json(CONFIG,data)

def libraries():
    return [dict(id=SINGLE,name='Single Assets',entries=[],workflow='manual',fixed=True)]+[x for x in state()['libraries'] if not x.get('removed') and x['id']!=SINGLE]

def get(cid):
    return next((x for x in state()['libraries'] if x['id']==cid),dict(id=SINGLE,name='Single Assets',entries=[],workflow='manual',fixed=True)) if cid==SINGLE else next(x for x in state()['libraries'] if x['id']==cid)

def create(name,config=None):
    import maxtree_license_manager
    maxtree_license_manager.require_authorization('asset_library_create')
    if not name.strip(): raise ValueError('Enter a library name')
    data=state();c=dict(id='library_'+uuid.uuid4().hex,name=name.strip(),entries=[],workflow=(config or {}).get('workflow','manual'),config=config or {},removed=False)
    data['libraries'].append(c);save(data);return c

def update(cid,**changes):
    import maxtree_license_manager
    maxtree_license_manager.require_authorization('asset_library_update')
    data=state();c=next(x for x in data['libraries'] if x['id']==cid)
    if 'name' in changes and not changes['name'].strip(): raise ValueError('Enter a library name')
    c.update(changes);save(data)

def all_paths(except_id=None):
    paths={}
    for c in store.effective_catalog()['entries']:
        paths.setdefault(store.identity(c['path']),[]).append(c['collection'])
    for c in state()['libraries']:
        if c.get('removed') or c['id']==except_id: continue
        for e in c['entries']:
            if isinstance(e,dict) and e.get('path'):paths.setdefault(store.identity(e['path']),[]).append(c['name'])
    return paths

def put(cid,entries,config=None,replace_id=None):
    import maxtree_license_manager
    maxtree_license_manager.require_authorization('asset_library_put')
    data=state();c=next((x for x in data['libraries'] if x['id']==cid),None)
    if c is None:
        if cid!=SINGLE: raise ValueError('Library does not exist')
        c=get(SINGLE);data['libraries'].append(c)
    current=list(c['entries'])
    if replace_id: current=[e for e in current if e['id']!=replace_id]
    lookup={store.identity(e['path']):i for i,e in enumerate(current) if isinstance(e,dict) and e.get('path')}
    for raw in entries:
        e=copy.deepcopy(raw);key=store.identity(e['path']);e['managed']=True;e['custom']=True
        e['collection']=cid;e['collection_label']=c['name'];e['renderer_policy']=e.get('renderer_policy','source')
        e['id']=replace_id or e.get('id') or uuid.uuid4().hex
        e['revision']=uuid.uuid4().hex
        if key in lookup:
            old=current[lookup[key]]
            e['id']=old['id']
            # Rescan retains display names and explicit reviewed paths.
            for field in ('display_name','category','manual_material','maps_folder','maps_folders','explicit_preview','map_overrides','review'):
                if old.get(field) and field not in e: e[field]=old[field]
            current[lookup[key]]=e
        else: lookup[key]=len(current);current.append(e)
    c['entries']=current
    if config is not None:
        c['config']=config
        c['maps_folder']=next(iter(config.get('folders',{}).get('maps',[])),'')
    save(data)

def remove_entry(cid,eid):
    import maxtree_license_manager
    maxtree_license_manager.require_authorization('asset_library_remove')
    update(cid,entries=[e for e in get(cid)['entries'] if e['id']!=eid])

def effective(e):
    if not isinstance(e,dict) or not e.get('path'):raise ValueError('Invalid asset record: missing MAX path')
    e=copy.deepcopy(e);e['custom']=True;e['managed']=True;e['_ref_kind']='managed'
    e.setdefault('variants',[]);e.setdefault('label',Path(e['path']).stem);e.setdefault('issues',[])
    review=e.get('review',{})
    obj=review.get('object')
    if obj and obj in e.get('object_names',[]):
        e['variants']=[dict(object=obj,name=obj,image=e.get('image',''),matlib='',material='')]
    if review.get('matlib'):e['manual_material']=review['matlib']
    image=e.get('explicit_preview') or review.get('preview') or e.get('image','')
    e['image']=image
    for v in e.get('variants',[]):v['image']=image
    e['map_overrides']=review.get('maps',e.get('map_overrides',{}))
    for match in e.get('map_matches',[]):
        target=e['map_overrides'].get(match.get('reference'))
        if target:match.update(resolved=target,status='resolved' if Path(target).is_file() else 'missing')
    e['label']=e.get('display_name') or e.get('label') or Path(e['path']).stem
    return e
