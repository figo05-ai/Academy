from pathlib import Path
import json, traceback
from pymxs import runtime as rt
OUT=Path(__file__).parent
data=json.loads((OUT/'scan_candidates.json').read_text(encoding='utf-8'))
assert rt.objects.count==0, 'Metadata audit must run in a clean batch session'
records=[]
for e in data['entries']:
    rec={'path':e['path']}
    try:
        rec['objects']=[str(n) for n in rt.getMAXFileObjectNames(e['path'])]
        rec['assets']=[{'filename':str(a.filename),'type':str(a.type)} for a in rt.getMAXFileAssetMetadata(e['path'])]
    except Exception:
        rec['error']=traceback.format_exc()
    records.append(rec)
    if len(records)%25==0:
        (OUT/'audit_progress.json').write_text(json.dumps({'processed':len(records),'total':len(data['entries']),'latest':e['collection']}))
(OUT/'model_metadata.json').write_text(json.dumps(records,indent=2),encoding='utf-8')
(OUT/'audit_progress.json').write_text(json.dumps({'processed':len(records),'complete':True}))

