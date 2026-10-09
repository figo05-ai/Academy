"""Collection settings and reference-only catalogs. Never writes source assets."""
from pathlib import Path
import copy, json, os, uuid, datetime
PROJECT = Path(__file__).resolve().parent

def resolve_user_settings_dir():
    """
    Resolves persistent user data settings directory.
    Priority:
    1. OFFICE_BLOCK_TEST_SETTINGS environment override (for tests/isolated validation)
    2. User APPDATA / config directory: %APPDATA%/Maxtree_Tool/settings (Windows) or ~/.config/Maxtree_Tool/settings (Linux)
    3. Non-destructive migration: If legacy settings exist in source folder and APPDATA doesn't,
       copy legacy settings to APPDATA without deleting source files.
    """
    if os.environ.get('OFFICE_BLOCK_TEST_SETTINGS'):
        return Path(os.environ['OFFICE_BLOCK_TEST_SETTINGS']).resolve()

    appdata = os.environ.get('APPDATA') or os.environ.get('XDG_CONFIG_HOME') or os.path.expanduser('~/.config')
    user_dir = Path(appdata) / 'Maxtree_Tool' / 'settings'

    source_dir = PROJECT / 'settings'
    legacy_config = source_dir / 'user_collections.json'
    target_config = user_dir / 'user_collections.json'

    if legacy_config.is_file() and not target_config.is_file():
        try:
            user_dir.mkdir(parents=True, exist_ok=True)
            import shutil
            shutil.copy2(legacy_config, target_config)
        except Exception:
            pass

    if not user_dir.exists():
        try:
            user_dir.mkdir(parents=True, exist_ok=True)
        except Exception:
            return source_dir

    return user_dir

SETTINGS = resolve_user_settings_dir()
CONFIG = SETTINGS / 'user_collections.json'

def identity(path):
    return os.path.normcase(os.path.realpath(os.path.abspath(str(path)))).casefold()

def read_json(path):
    return json.loads(Path(path).read_text(encoding='utf-8-sig'))

def atomic_json(path, data):
    path = Path(path)
    # All writes must stay in this tool's settings directory.
    path.resolve().relative_to(SETTINGS.resolve())
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_name(path.name + '.' + uuid.uuid4().hex + '.tmp')
    tmp.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding='utf-8')
    os.replace(tmp, path)

def load_state():
    if not CONFIG.exists():
        return {'schema':1, 'collections':[]}
    state = read_json(CONFIG)
    if state.get('schema') != 1:
        raise ValueError('Unsupported user settings version; existing settings were not changed.')
    return state

def save_state(state):
    if CONFIG.exists():
        stamp = datetime.datetime.now().strftime('%Y%m%d_%H%M%S_%f')
        atomic_json(SETTINGS / 'history' / (stamp + '.json'), load_state())
    atomic_json(CONFIG, state)

def create_collection(name, folders, renderer='corona'):
    import maxtree_license_manager
    maxtree_license_manager.require_authorization('create_collection')
    if not name.strip():
        raise ValueError('Enter a collection name.')
    clean = {}
    for role in ('mixed','models','previews','maps'):
        clean[role] = list(dict.fromkeys(str(Path(p).resolve()) for p in folders.get(role, []) if p))
        for folder in clean[role]:
            if not Path(folder).is_dir():
                raise ValueError('Folder not found: ' + folder)
    if not (clean['mixed'] or clean['models']):
        raise ValueError('Choose a model folder or a mixed asset folder.')
    state = load_state()
    item = {'id':'user_'+uuid.uuid4().hex, 'name':name.strip(), 'folders':clean,
            'renderer':renderer, 'reviews':{}, 'removed':False, 'scan':None}
    state['collections'].append(item)
    save_state(state)
    return item

def get_collection(cid):
    return next(c for c in load_state()['collections'] if c['id']==cid)

def rename_collection(cid, name):
    import maxtree_license_manager
    maxtree_license_manager.require_authorization('rename_collection')
    if not name.strip():
        raise ValueError('Enter a collection name.')
    state=load_state()
    next(c for c in state['collections'] if c['id']==cid)['name']=name.strip()
    save_state(state)

def remove_collection(cid):
    import maxtree_license_manager
    maxtree_license_manager.require_authorization('remove_collection')
    state=load_state()
    next(c for c in state['collections'] if c['id']==cid)['removed']=True
    save_state(state)

def commit_scan(cid, result_path):
    import maxtree_license_manager
    maxtree_license_manager.require_authorization('commit_scan')
    result=read_json(result_path)
    if result.get('collection_id')!=cid or not result.get('complete'):
        raise ValueError('Incomplete scan; previous catalog retained.')
    state=load_state()
    item=next(c for c in state['collections'] if c['id']==cid)
    if item.get('removed'):
        return
    # Keep previous scan results; change only the current reference.
    item['scan']=str(Path(result_path).resolve())
    item['last_scan']=datetime.datetime.now().isoformat()
    save_state(state)

def save_review(cid, model, review):
    import maxtree_license_manager
    maxtree_license_manager.require_authorization('save_review')
    state=load_state()
    item=next(c for c in state['collections'] if c['id']==cid)
    item.setdefault('reviews',{})[identity(model)]=review
    save_state(state)

def effective_catalog():
    base=read_json(PROJECT/'catalog.json')
    if base.get('schema')!=2:
        raise ValueError('Unsupported base catalog format.')
    data=copy.deepcopy(base)
    for cid,meta in data['collections'].items():
        meta.setdefault('name',cid)
    entries=[]
    by_path={}
    for raw in data['entries']:
        e=dict(raw)
        e['memberships']=[e['collection']]
        e.setdefault('issues',[])
        k=identity(e['path'])
        if k not in by_path:
            by_path[k]=e
            entries.append(e)
    for c in load_state()['collections']:
        if c.get('removed'):
            continue
        try:
            scan=read_json(c['scan']) if c.get('scan') and Path(c['scan']).is_file() else {'entries':[],'images':{}}
        except (OSError,ValueError) as exc:
            data.setdefault('errors',[]).append('Cannot read collection '+c.get('name','')+': '+str(exc))
            scan={'entries':[],'images':{}}
        roots=[p for paths in c['folders'].values() for p in paths]
        data['collections'][c['id']]={'name':c['name'],'images':scan.get('images',{}),
            'roots':roots,'root':roots[0] if roots else '', 'custom':True,'maps_folders':c['folders'].get('maps',[])}
        for raw in scan['entries']:
            if not isinstance(raw,dict) or not raw.get('path'):
                data.setdefault('errors',[]).append('Invalid asset record in '+c['name']);continue
            e=copy.deepcopy(raw)
            e['collection']=c['id']
            e['collection_label']=c['name']
            e['memberships']=[c['id']]
            e['custom']=True
            e['renderer_policy']=c.get('renderer','corona')
            review=c.get('reviews',{}).get(identity(e['path']),{})
            e['map_overrides']=review.get('maps',{})
            if review.get('object') in e.get('object_names',[]):
                e['variants']=[{'name':review['object'],'object':review['object'],
                    'image':review.get('preview') or e.get('image',''),
                    'material':review.get('material',''),'matlib':review.get('matlib','')}]
                e['object_confirmed']=True
            if review.get('preview'):
                e['image']=review['preview']
                for v in e['variants']:
                    v['image']=review['preview']
            if review.get('matlib'):
                for v in e['variants']:
                    v['matlib']=review['matlib']
                    v['material']=review.get('material','')
            e['review']=review
            remaining=[]
            for issue in e.get('issues',[]):
                if issue.startswith('Object review') and review.get('object'): continue
                if ('preview' in issue.casefold()) and review.get('preview'): continue
                if any(issue.endswith(ref) for ref in review.get('maps',{})): continue
                remaining.append(issue)
            e['issues']=remaining
            key=identity(e['path'])
            if key in by_path:
                # One visible model path, retaining base proof and all collection memberships.
                by_path[key]['memberships'].append(c['id'])
                by_path[key].setdefault('custom_contexts',{})[c['id']]=e
            else:
                by_path[key]=e
                entries.append(e)
    data['entries']=entries
    return data

def entry_context(entry, cid):
    if cid in entry.get('custom_contexts',{}):
        custom=entry['custom_contexts'][cid]
        # Explicit review choices take precedence only in that custom collection.
        return custom
    return entry

