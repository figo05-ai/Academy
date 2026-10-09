from pathlib import Path
p=Path(__file__).parent/'maxtree_library.py'
s=p.read_text(encoding='utf-8-sig')
def replace(old,new):
    global s
    if old not in s: raise RuntimeError('Patch target not found: '+old[:100])
    s=s.replace(old,new,1)
replace("PROJECT = Path(__file__).resolve().parent","PROJECT = Path(__file__).resolve().parent\nimport sys\nif str(PROJECT) not in sys.path: sys.path.insert(0,str(PROJECT))\nimport collection_store as collection_store\nfrom collection_ui import CollectionDialog, ScanJob, ReviewDialog")
start=s.index("    data = json.loads((PROJECT / 'catalog.json')")
end=s.index("\ndef catalog():",start)
s=s[:start]+"    return collection_store.effective_catalog()\n"+s[end:]
replace("            if not e['variants']:\n                raise ValueError('No verified plant objects: ' + e['name'])",
        "            e['collection_label'] = catalog_data()['collections'][e['collection']].get('name',e['collection'])")
replace("    normalized = old.replace('\\\\', '/')",r"""    if entry.get('custom'):
        overrides=entry.get('map_overrides',{})
        target=overrides.get(old)
        if not target:
            same=[v for k,v in overrides.items() if k.replace('\\','/').rsplit('/',1)[-1].casefold()==old.replace('\\','/').rsplit('/',1)[-1].casefold()]
            if len(set(same))==1: target=same[0]
        if target:
            return (Path(target),None) if Path(target).is_file() else (None,'Reviewed map is missing: '+target)
        if old in overrides.values() and Path(old).is_file():
            return Path(old),None
        matches=[m for m in entry.get('map_matches',[]) if m['reference'].replace('\\','/').rsplit('/',1)[-1].casefold()==old.replace('\\','/').rsplit('/',1)[-1].casefold()]
        resolved={m['resolved'] for m in matches if m.get('resolved')}
        if matches and len(resolved)==1 and all(m.get('resolved') for m in matches):
            target=next(iter(resolved))
            return (Path(target),None) if Path(target).is_file() else (None,'Scanned map is now missing: '+target)
        if matches:
            return None,'Review required for map: '+old
        candidates=catalog_data()['collections'][entry['collection']]['images'].get(old.replace('\\','/').rsplit('/',1)[-1].casefold(),[])
        candidates=[Path(v) for v in candidates if Path(v).is_file()]
        if len(candidates)==1:
            return candidates[0],None
        return None,('Ambiguous map: ' if candidates else 'Missing map: ')+old
    normalized = old.replace('\\', '/')""")
replace("    if not any(c.startswith('corona') for c in classes):",
        "    if entry.get('renderer_policy','corona') == 'corona' and not any(c.startswith('corona') for c in classes):")
replace("    if any(c.startswith('vraymtl') for c in classes):",
        "    if entry.get('renderer_policy','corona') == 'corona' and any(c.startswith('vraymtl') for c in classes):")
replace("        variant = entry['variants'][variant_index]",
        "        if not entry.get('variants'):\n            raise RuntimeError('Choose the exact block in Review matches before insertion.')\n        variant = entry['variants'][variant_index]")
replace("        self.entries, errors = catalog()",
        "        self.entries, errors = catalog()\n        self.scan_job = None")
replace("        self.collection.addItems(['All collections'] + sorted({e['collection'] for e in self.entries}))",
        "        self.collection.addItem('All collections', None)\n        for cid,meta in catalog_data()['collections'].items():\n            self.collection.addItem(meta.get('name',cid),cid)")
replace("        body = QtWidgets.QHBoxLayout()",r"""        management = QtWidgets.QHBoxLayout()
        self.create_button = QtWidgets.QPushButton('Create Collection…')
        self.rename_button = QtWidgets.QPushButton('Rename')
        self.rescan_button = QtWidgets.QPushButton('Rescan')
        self.remove_button = QtWidgets.QPushButton('Remove collection')
        self.cancel_scan_button = QtWidgets.QPushButton('Cancel scan')
        self.review_button = QtWidgets.QPushButton('Review matches…')
        for button in (self.create_button,self.rename_button,self.rescan_button,self.remove_button,self.cancel_scan_button,self.review_button):
            management.addWidget(button)
        layout.addLayout(management)
        self.create_button.clicked.connect(self.create_collection)
        self.rename_button.clicked.connect(self.rename_collection)
        self.rescan_button.clicked.connect(self.rescan_collection)
        self.remove_button.clicked.connect(self.remove_collection)
        self.cancel_scan_button.clicked.connect(lambda:self.scan_job.cancel() if self.scan_job else None)
        self.review_button.clicked.connect(self.review_matches)
        body = QtWidgets.QHBoxLayout()""")
replace("        self.insert_button = QtWidgets.QPushButton('Insert selected Corona plant at origin')",
        "        self.insert_button = QtWidgets.QPushButton('Insert selected plant at origin')")
replace("        species, collection = self.species.currentText(), self.collection.currentText()",
        "        species, collection = self.species.currentText(), self.collection.currentData()")
replace("            (collection == 'All collections' or e['collection'] == collection) and",
        "            (collection is None or collection in e.get('memberships',[e['collection']])) and")
replace("            query in (e['name']+' '+e['label']+' '+e['collection']+' '+' '.join(v['name'] for v in e['variants'])).casefold()]",
        "            query in (e['name']+' '+e['label']+' '+e.get('collection_label',e['collection'])+' '+' '.join(v['name'] for v in e['variants'])).casefold()]\n        custom = bool(collection and catalog_data()['collections'][collection].get('custom'))\n        for button in (self.rename_button,self.rescan_button,self.remove_button): button.setEnabled(custom and not self.scan_job)\n        self.create_button.setEnabled(not self.scan_job)\n        self.cancel_scan_button.setEnabled(bool(self.scan_job))")
replace("            item = QtWidgets.QListWidgetItem(e['label']+'\\n'+e['collection'])",
        "            e = dict(collection_store.entry_context(e,self.collection.currentData()))\n            e['path'] = Path(e['path'])\n            label=catalog_data()['collections'][self.collection.currentData() or e['collection']].get('name',e['collection'])\n            item = QtWidgets.QListWidgetItem(e['label']+'\\n'+label)")
replace("        self.details.setText(e['collection']+'\\n'+str(e['path'].relative_to(Path(e['collection_root'])))+\n            '\\n'+str(len(e['variants']))+' plant variant(s)')",
        "        issues=e.get('issues',[])\n        label=catalog_data()['collections'][self.collection.currentData() or e['collection']].get('name',e['collection'])\n        self.details.setText(label+'\\n'+str(e['path'])+'\\n'+str(len(e['variants']))+' plant variant(s)\\n'+('\\n'.join(issues[:4]) if issues else 'Links available')+'\\nMaterials: '+e.get('renderer_policy','corona'))\n        self.details.setToolTip('\\n'.join(issues))\n        self.review_button.setEnabled(bool(e.get('custom')))\n        self.insert_button.setEnabled(bool(e['variants']) and not self.scan_job)")
replace("        if item is None or self.variant.currentIndex() < 0:\n            return",
        "        if item is None:\n            return\n        if self.variant.currentIndex() < 0:\n            self.preview.setText('Block not identified. Use Review matches.')\n            return")
old="""            import pymxs
            with pymxs.undo(True, 'Insert Maxtree plant'):
                node, fixed, warnings = self.importer.insert(item.data(QtCore.Qt.UserRole),
                    mode=self.mode.currentText(), variant_index=self.variant.currentIndex())"""
new="""            # mergeMAXFile owns its native merge transaction; do not nest it in
            # a pymxs undo block (the previous wrapper crashed Max 2027 on Undo).
            node, fixed, warnings = self.importer.insert(item.data(QtCore.Qt.UserRole),
                mode=self.mode.currentText(), variant_index=self.variant.currentIndex())"""
replace(old,new)
methods=r"""
    def refresh_catalog(self, selected=None):
        catalog_data.cache_clear()
        self.entries,errors=catalog()
        self.collection.blockSignals(True)
        self.collection.clear()
        self.collection.addItem('All collections',None)
        for cid,meta in catalog_data()['collections'].items():
            self.collection.addItem(meta.get('name',cid),cid)
        if selected:
            index=self.collection.findData(selected)
            if index>=0: self.collection.setCurrentIndex(index)
        self.collection.blockSignals(False)
        self.species.blockSignals(True)
        self.species.clear()
        self.species.addItems(['All species']+sorted({e['species'] for e in self.entries}))
        self.species.blockSignals(False)
        self.search.clear()
        self.filter_entries()

    def create_collection(self):
        dialog=CollectionDialog(self)
        if dialog.exec()==QtWidgets.QDialog.Accepted:
            try:
                c=dialog.create()
                self.refresh_catalog(c['id'])
                self.start_scan(c['id'])
            except Exception as exc:
                QtWidgets.QMessageBox.warning(self,TITLE,str(exc))

    def rename_collection(self):
        cid=self.collection.currentData()
        if not cid or not cid.startswith('user_'): return
        name,ok=QtWidgets.QInputDialog.getText(self,'Rename collection','Collection name:',text=self.collection.currentText())
        if ok:
            try:
                collection_store.rename_collection(cid,name)
                self.refresh_catalog(cid)
            except Exception as exc:
                QtWidgets.QMessageBox.warning(self,TITLE,str(exc))

    def remove_collection(self):
        cid=self.collection.currentData()
        if not cid or not cid.startswith('user_'): return
        if QtWidgets.QMessageBox.question(self,'Remove collection','Remove this collection from the browser? Source files will not be deleted.')==QtWidgets.QMessageBox.Yes:
            collection_store.remove_collection(cid)
            self.refresh_catalog()

    def rescan_collection(self):
        cid=self.collection.currentData()
        if cid and cid.startswith('user_'): self.start_scan(cid)

    def start_scan(self,cid):
        if self.scan_job: raise RuntimeError('A scan is already running.')
        self.scan_job=ScanJob(cid,self)
        self.scan_job.progress.connect(self.status.setText)
        self.scan_job.finished.connect(self.scan_finished)
        try:
            self.scan_job.start()
            self.filter_entries()
        except Exception:
            self.scan_job=None
            raise

    def scan_finished(self,cid,ok,message):
        old=self.scan_job
        self.scan_job=None
        self.refresh_catalog(cid)
        self.status.setText(message)
        if old: old.deleteLater()

    def review_matches(self):
        item=self.grid.currentItem()
        if not item: return
        e=item.data(QtCore.Qt.UserRole)
        if not e.get('custom'): return
        dialog=ReviewDialog(e,self)
        if dialog.exec()==QtWidgets.QDialog.Accepted:
            try:
                collection_store.save_review(e['collection'],e['path'],dialog.review())
                self.refresh_catalog(e['collection'])
            except Exception as exc:
                QtWidgets.QMessageBox.warning(self,TITLE,str(exc))

    def closeEvent(self,event):
        if self.scan_job:
            self.scan_job.cancel()
            self.status.setText('Cancelling scan; close again when it finishes.')
            event.ignore()
        else:
            event.accept()

"""
replace("\ndef show():",methods+"\ndef show():")
p.write_text(s,encoding='utf-8')
print('Updated existing browser')

