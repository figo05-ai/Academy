"""General block insertion. Source files are read only; only new nodes are changed."""
from pathlib import Path
from contextlib import contextmanager
from collections import defaultdict
import os
from pymxs import runtime as rt

class MapReviewError(RuntimeError):
    def __init__(self,records):
        self.records=records
        self.summary=summary(records)
        lines=['Map review required. Insertion was rolled back.',summary_text(records)]
        for r in records:
            if r['status']!='resolved':lines.append(r['status']+': '+r['reference']+'\n'+'\n'.join(r['candidates']))
        super().__init__('\n'.join(lines))

def summary(records):return {k:sum(r['status']==k for r in records) for k in ('resolved','missing','ambiguous')}
def summary_text(records):return 'Maps: '+', '.join(k+' '+str(v) for k,v in summary(records).items())
def node_handle(n):return int(rt.getHandleByAnim(n))
def roots(nodes):
    handles={node_handle(n) for n in nodes}
    return [n for n in nodes if n.parent is None or node_handle(n.parent) not in handles]

def map_roots(entry):
    paths=[]
    if entry.get('maps_folder'):paths=[entry['maps_folder']]
    elif entry.get('maps_folders'):paths=entry['maps_folders']
    elif entry.get('_library_maps'):paths=entry['_library_maps']
    else:
        try:
            import maxtree_library as lib
            meta=lib.catalog_data()['collections'][entry['collection']]
            paths=meta.get('maps_folders',[])
            if not paths:paths=list(dict.fromkeys(str(Path(p).parent) for values in meta.get('images',{}).values() for p in values))
        except (KeyError,ValueError):pass
    return list(dict.fromkeys(str(Path(x).resolve()) for x in paths if x))

def image_index(folders):
    from collection_scan import IMAGE_EXT
    index=defaultdict(list);seen=set()
    for folder in folders:
        if not Path(folder).is_dir():continue
        for directory,dirs,files in os.walk(folder,followlinks=False):
            dirs[:]=[d for d in dirs if not Path(directory,d).is_symlink()]
            for filename in files:
                p=Path(directory,filename)
                if p.suffix.casefold() not in IMAGE_EXT:continue
                key=os.path.normcase(str(p.resolve()))
                if key not in seen:index[p.name.casefold()].append(str(p.resolve()));seen.add(key)
    return dict(index)

@contextmanager
def temporary_search_paths(folders):
    previous=[str(rt.mapPaths.get(i)) for i in range(1,int(rt.mapPaths.count())+1)]
    try:
        for folder in folders:
            if Path(folder).is_dir() and folder.casefold() not in {p.casefold() for p in previous}:rt.mapPaths.add(folder)
        yield
    finally:
        current=[str(rt.mapPaths.get(i)) for i in range(1,int(rt.mapPaths.count())+1)]
        if current!=previous:
            for i in range(int(rt.mapPaths.count()),0,-1):rt.mapPaths.delete(i)
            for folder in previous:rt.mapPaths.add(folder)
        restored=[str(rt.mapPaths.get(i)) for i in range(1,int(rt.mapPaths.count())+1)]
        if restored!=previous:raise RuntimeError('Could not restore the original session map search paths.')

def resolve(old,entry,index,source_refs):
    bn=old.replace('\\','/').rsplit('/',1)[-1].casefold()
    chosen=entry.get('map_overrides',{}).get(old)
    if not chosen:
        original=source_refs.get(bn,[])
        overrides={entry.get('map_overrides',{}).get(x) for x in original}-{None,''}
        if len(overrides)==1:chosen=next(iter(overrides))
    candidates=list(dict.fromkeys(index.get(bn,[])))
    if chosen:return dict(reference=old,candidates=candidates,resolved=chosen if Path(chosen).is_file() else '',status='resolved' if Path(chosen).is_file() else 'missing')
    original=source_refs.get(bn,[])
    # Max may find a texture while merging; never accept an arbitrary duplicate
    # merely because native lookup changed its filename to an existing candidate.
    was_unresolved=bool(original) and not any(Path(x).is_file() for x in original)
    if Path(old).is_file() and not was_unresolved:
        return dict(reference=old,candidates=candidates,resolved=str(Path(old).resolve()),status='resolved')
    valid_original=[x for x in original if Path(x).is_file()]
    if len(valid_original)==1:return dict(reference=old,candidates=candidates,resolved=valid_original[0],status='resolved')
    return dict(reference=old,candidates=candidates,resolved=candidates[0] if len(candidates)==1 else '',status='resolved' if len(candidates)==1 else ('ambiguous' if candidates else 'missing'))

def repair(nodes,entry,index,source_refs):
    import maxtree_library as lib
    records=[];seen=set();classes=[]
    for node in nodes:
        if not node.material:continue
        for obj in lib.material_tree(node.material):
            h=int(rt.getHandleByAnim(obj))
            if h in seen:continue
            seen.add(h);cls=str(rt.classOf(obj)).casefold();classes.append(cls)
            if 'missing' in cls:raise RuntimeError('Missing material/map plugin: '+cls)
            for prop in ('filename','fileName'):
                if rt.isProperty(obj,prop):
                    old=str(rt.getProperty(obj,prop) or '')
                    if old:
                        record=resolve(old,entry,index,source_refs);records.append(record)
                        if record['resolved']:rt.setProperty(obj,prop,record['resolved'])
                    break
    entry['_last_map_report']=records
    if any(x['status']!='resolved' for x in records):raise MapReviewError(records)
    if entry.get('renderer_policy','source')=='corona' and classes and not any(c.startswith('corona') for c in classes):raise RuntimeError('Corona materials were not verified; choose an appropriate library renderer policy.')
    return records

def object_choice(entry,names,variant_index=0,parent=None):
    if len(names)==1:return [names[0]],False
    requested=entry.get('_object_choice')
    if requested:
        chosen=list(requested['names']);full=bool(requested.get('full'))
        if not chosen or any(x not in names for x in chosen):raise RuntimeError('Reviewed objects are absent from the MAX file.')
        return chosen,full
    from block_actions import ObjectDialog
    d=ObjectDialog(names,entry,variant_index,parent)
    from PySide6 import QtWidgets
    if d.exec()!=QtWidgets.QDialog.Accepted:raise RuntimeError('Block selection cancelled; nothing inserted.')
    return d.choice()

def insert(importer,entry,renderer='corona',mode='Copy',variant_index=0,placement=None,parent=None):
    import maxtree_license_manager
    maxtree_license_manager.require_authorization('model_insertion')
    path=Path(entry['path'])
    if not path.is_file():raise RuntimeError('MAX file not found: '+str(path))
    names=[str(n) for n in rt.getMAXFileObjectNames(str(path))]
    if not names:raise RuntimeError('No readable objects in this MAX file.')
    chosen,full=object_choice(entry,names,variant_index,parent)
    if not full and len(chosen)!=len(set(chosen)):raise RuntimeError('Duplicate object names require explicit full-block selection.')
    source_refs=defaultdict(list)
    for a in rt.getMAXFileAssetMetadata(str(path)):
        value=str(a.filename);source_refs[value.replace('\\','/').rsplit('/',1)[-1].casefold()].append(value)
    folders=map_roots(entry);index=image_index(folders)
    # Legacy collection indexes remain usable when no dedicated maps folder exists.
    if not folders:
        index=entry.get('_images',{})
        if not index:
            try:
                import maxtree_library as lib
                index=lib.catalog_data()['collections'][entry['collection']].get('images',{})
            except KeyError:pass
    previous=list(rt.objects);before={node_handle(n) for n in previous};selection=list(rt.selection)
    if placement=='selection':
        if len(selection)!=1:raise RuntimeError('Select exactly one existing object to use its pivot, or choose Origin.')
        target=rt.Point3(*list(selection[0].position))
    else:target=rt.Point3(*(placement or (0,0,0)))
    key=(str(path.resolve()).casefold(),tuple(chosen),full,entry.get('id',''),entry.get('revision',''),entry.get('manual_material',''))
    cached=importer.sources.get(key)
    created=[]
    try:
        with temporary_search_paths(folders):
            if cached and all(rt.isValidNode(n) for n in cached):
                result=rt.maxOps.cloneNodes(rt.Array(*cached),cloneType=rt.Name('instance' if mode=='Instance' else 'copy'),expandHierarchy=True)
            else:
                result=rt.mergeMAXFile(str(path),rt.Array(*chosen),rt.Name('select'),rt.Name('autoRenameDups'),rt.Name('renameMtlDups'),quiet=True)
            created=[n for n in rt.objects if node_handle(n) not in before]
            if not result or not created:raise RuntimeError('The merge produced no objects.')
            if not full and not cached and sum(not rt.isGroupHead(n) for n in created)>len(chosen):raise RuntimeError('The selection added dependent objects. Review and explicitly select the full block if intended.')
            copies={}
            for n in created:
                if 'missing' in str(rt.classOf(n)).casefold():raise RuntimeError('A required object plugin is missing.')
                if n.material:
                    h=int(rt.getHandleByAnim(n.material))
                    if h not in copies:copies[h]=rt.copy(n.material)
                    n.material=copies[h]
                # Preserve the existing optional MAT workflow only when explicitly supplied.
                if entry.get('manual_material') and entry.get('use_material_library',True) and n.material:
                    import asset_materials
                    asset_materials.assign(n,entry,parent)
            records=repair(created,entry,index,source_refs)
            top=roots(created)
            if len(top)>1:
                head=rt.group(rt.Array(*top),name=rt.uniqueName(entry.get('display_name') or entry.get('label') or path.stem))
                created.append(head);top=[head]
            if not top:raise RuntimeError('The imported hierarchy has no root.')
            delta=target-top[0].position
            for n in top:n.position=n.position+delta
            rt.select(rt.Array(*top));importer.sources[key]=top
            importer.last_nodes=list(created);importer.last_summary=summary(records)
            rt.redrawViews()
            return top[0],[r['resolved'] for r in records],[]
    except Exception:
        # The set difference is scoped to this synchronous insertion, not selection.
        fresh=[n for n in rt.objects if node_handle(n) not in before]
        if fresh:rt.delete(rt.Array(*fresh))
        rt.select(rt.Array(*[n for n in selection if rt.isValidNode(n)]))
        raise
