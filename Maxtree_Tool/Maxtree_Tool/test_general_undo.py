assert result['result']=='PASS' and post['result']=='PASS'
assert os.getpid()==collection_store.read_json(OUT/'ready.json')['pid']
assert str(collection_store.SETTINGS).startswith(str(OUT))
undo_report=dict(result='RUNNING',cases=[],scope='Dedicated validation session only',pid=os.getpid())
write('undo_results.json',undo_report)
undo_cases=['single_merge','multi_merge','copy_clone','instance_clone']
def undo_case(index=0):
    try:
        if index>=len(undo_cases):
            undo_report['result']='PASS' if all(x['result']=='PASS' for x in undo_report['cases']) else 'LIMITATION'
            write('undo_results.json',undo_report);return
        name=undo_cases[index]
        rt.resetMaxFile(rt.Name('noPrompt'))
        keep=rt.box(name='UNDO_PRESERVE',length=1,width=1,height=1);keep.material=rt.StandardMaterial(name='UndoExisting');handle=int(rt.getHandleByAnim(keep))
        entry=dict(path=fixtures['MultiBlock' if name=='multi_merge' else 'SingleBlock']['path'],label='Undo fixture',renderer_policy='source',maps_folder=str(FIX/'Maps'))
        if name=='multi_merge':entry['_object_choice']=dict(names=fixtures['MultiBlock']['objects'],full=True)
        importer=maxtree_library.Importer()
        if name in ('copy_clone','instance_clone'):importer.insert(entry)
        rt.clearUndoBuffer();before={int(rt.getHandleByAnim(n)) for n in rt.objects}
        importer.insert(entry,mode='Instance' if name=='instance_clone' else 'Copy')
        undo_report['pending']=dict(case=name,action='native max undo',before=sorted(before),inserted=len(list(rt.objects))-len(before))
        write('undo_results.json',undo_report)
        rt.execute('max undo')
        after={int(rt.getHandleByAnim(n)) for n in rt.objects}
        item=dict(case=name,result='PASS' if after==before else 'FAIL',preexisting_preserved=handle in after,expected_objects=len(before),actual_objects=len(after))
        undo_report['cases'].append(item);undo_report.pop('pending',None);write('undo_results.json',undo_report)
        QtCore.QTimer.singleShot(1000,lambda:undo_case(index+1))
    except Exception:
        undo_report['result']='FAILED_UNSAFE_OR_UNSUPPORTED';undo_report['error']=traceback.format_exc();write('undo_results.json',undo_report)
QtCore.QTimer.singleShot(1000,undo_case)
