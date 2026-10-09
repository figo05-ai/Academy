"""Reference overlays for the original catalog. Never edits the catalog or source assets."""
import copy,datetime,uuid
from pathlib import Path
import collection_store as collections
import asset_store as assets
CONFIG=collections.SETTINGS/'block_overrides.json'

def state():return collections.read_json(CONFIG) if CONFIG.exists() else dict(schema=1,overrides={},added={})
def save(data):
    if CONFIG.exists():collections.atomic_json(collections.SETTINGS/'history'/('blocks_'+datetime.datetime.now().strftime('%Y%m%d_%H%M%S_%f')+'.json'),state())
    collections.atomic_json(CONFIG,data)
def key(cid,path):return cid+'|'+collections.identity(path)
def clean(entry):
    result={k:copy.deepcopy(v) for k,v in entry.items() if not k.startswith('_') and k not in ('custom_contexts','memberships')}
    for k in ('path','image'):
        if result.get(k):result[k]=str(result[k])
    if not result.get('maps_folder') and not result.get('maps_folders'):
        import block_runtime
        result['maps_folders']=block_runtime.map_roots(entry)
    return result

def apply(data):
    overlay=state();output=[];lookup={};errors=[]
    for cid,folder in overlay.get('library_maps',{}).items():
        if cid in data['collections']:data['collections'][cid]['maps_folders']=[folder]
    rows=[]
    for original in data['entries']:
        for cid in original.get('memberships',[original['collection']]):
            rows.append((cid,collections.entry_context(original,cid)))
    for cid,entries in overlay.get('added',{}).items():
        if cid in data['collections']:rows.extend((cid,e) for e in entries)
    for cid,raw in rows:
        try:
            e=copy.deepcopy(raw);ref=key(cid,e['path']);changes=overlay.get('overrides',{}).get(ref,{})
            if changes.get('removed'):continue
            e.update(changes);e['_reference_key']=ref;e['_ref_kind']='base';e['collection']=cid
            e['label']=e.get('display_name') or e.get('label') or Path(e['path']).stem
            e.setdefault('category','Plants' if not e.get('custom') else 'General')
            e.setdefault('variants',[]);e.setdefault('image','');e.setdefault('issues',[])
            review=e.get('review',{})
            if review.get('preview'):
                e['image']=review['preview']
                for v in e['variants']:v['image']=e['image']
            e['map_overrides']=review.get('maps',e.get('map_overrides',{}))
            e['memberships']=[cid]
            k=collections.identity(e['path'])
            if k in lookup:
                existing=lookup[k]
                if cid not in existing['memberships']:existing['memberships'].append(cid)
                existing.setdefault('custom_contexts',{})[cid]=e
            else:lookup[k]=e;output.append(e)
        except Exception as exc:errors.append('Invalid block reference in '+str(cid)+': '+str(exc))
    data['entries']=output;data['errors']=data.get('errors',[])+errors
    return data

def edit(entry,changes):
    import maxtree_license_manager
    maxtree_license_manager.require_authorization('catalog_edit')
    if entry.get('_ref_kind')=='base':
        data=state();ref=entry.get('_reference_key') or key(entry['collection'],entry['path']);data['overrides'].setdefault(ref,{}).update(changes);save(data)
    else:
        e=clean(entry);e.update(changes);assets.put(entry['collection'],[e],replace_id=entry['id'])

def remove(entry):
    import maxtree_license_manager
    maxtree_license_manager.require_authorization('catalog_remove')
    if entry.get('_ref_kind')=='base':edit(entry,dict(removed=True))
    else:assets.remove_entry(entry['collection'],entry['id'])

def destinations():
    data=collections.effective_catalog()
    return [('Library: '+c['name'],('managed',c['id'])) for c in assets.libraries()]+[('Catalog: '+m.get('name',cid),('base',cid)) for cid,m in data['collections'].items()]

def move(entry,target_kind,target_id):
    import maxtree_license_manager
    maxtree_license_manager.require_authorization('catalog_move')
    import maxtree_library as lib
    source_kind=entry.get('_ref_kind','managed');source_id=entry['collection']
    if (source_kind,source_id)==(target_kind,target_id):return
    if target_kind=='managed':target_entries=assets.get(target_id)['entries']
    else:target_entries=[collections.entry_context(e,target_id) for e in lib.catalog_data()['entries'] if target_id in e.get('memberships',[e['collection']])]
    if any(collections.identity(e['path'])==collections.identity(entry['path']) for e in target_entries):raise ValueError('This MAX path already exists in the destination. The reference was not moved.')
    raw=clean(entry);raw['collection']=target_id;raw['id']=uuid.uuid4().hex
    before_assets=assets.state();before_overlay=state()
    try:
        if source_kind=='managed' and target_kind=='managed':
            data=assets.state();source=next(c for c in data['libraries'] if c['id']==source_id)
            target=next((c for c in data['libraries'] if c['id']==target_id),None)
            if target is None:
                target=assets.get(target_id);data['libraries'].append(target)
            source['entries']=[e for e in source['entries'] if e['id']!=entry['id']]
            target['entries'].append(raw);target.setdefault('images',{}).update(entry.get('_images',{}));assets.save(data)
        else:
            overlay=state()
            if target_kind=='base':
                target_ref=key(target_id,raw['path'])
                if overlay['overrides'].get(target_ref,{}).get('removed'):
                    overlay['overrides'][target_ref].update(raw);overlay['overrides'][target_ref]['removed']=False
                else:overlay.setdefault('added',{}).setdefault(target_id,[]).append(raw)
            else:assets.put(target_id,[raw])
            if source_kind=='base':overlay['overrides'].setdefault(entry.get('_reference_key') or key(source_id,entry['path']),{})['removed']=True
            else:assets.remove_entry(source_id,entry['id'])
            save(overlay)
    except Exception:
        assets.save(before_assets);save(before_overlay);raise
