"""مكتبة نباتات المكتب الفني — Corona catalog; source libraries remain read-only."""
from pathlib import Path
import json, hashlib, traceback
from functools import lru_cache
from pymxs import runtime as rt
from PySide6 import QtCore, QtGui, QtWidgets

PROJECT = Path(__file__).resolve().parent
import sys
if str(PROJECT) not in sys.path: sys.path.insert(0,str(PROJECT))
import collection_store as collection_store
from collection_ui import CollectionDialog, ScanJob, ReviewDialog
TITLE = 'مكتبة نباتات المكتب الفني'

@lru_cache(maxsize=1)
def catalog_data():
    return collection_store.effective_catalog()

def catalog():
    entries, errors, seen = [], [], set()
    for raw in catalog_data()['entries']:
        try:
            e = dict(raw)
            e['path'] = Path(e['path'])
            identity = str(e['path'].resolve()).casefold()
            if identity in seen:
                continue
            seen.add(identity)
            e['image'] = Path(e['image']) if e.get('image') else None
            e['collection_label'] = catalog_data()['collections'][e['collection']].get('name',e['collection'])
            entries.append(e)
        except Exception as exc:
            errors.append(str(exc))
    return entries, errors

def material_tree(material):
    seen = set()
    def walk(obj):
        if obj is None:
            return
        ident = int(rt.getHandleByAnim(obj))
        if ident in seen:
            return
        seen.add(ident)
        yield obj
        for count, getter in ((rt.getNumSubMtls, rt.getSubMtl), (rt.getNumSubTexmaps, rt.getSubTexmap)):
            try:
                children = [getter(obj, i) for i in range(1, int(count(obj)) + 1)]
            except RuntimeError:
                children = []
            for child in children:
                yield from walk(child)
    yield from walk(material)

@lru_cache(maxsize=4096)
def digest(path, size, modified):
    h = hashlib.sha256()
    with open(path, 'rb') as stream:
        for block in iter(lambda: stream.read(1024*1024), b''):
            h.update(block)
    return h.hexdigest()

def resolve_texture(old, entry):
    if entry.get('managed'):
        reviewed=entry.get('map_overrides',{}).get(old)
        if reviewed:
            return (Path(reviewed),None) if Path(reviewed).is_file() else (None,'Reviewed map missing: '+reviewed)
        if Path(old).is_file():return Path(old),None
        basename=old.replace('\\','/').rsplit('/',1)[-1].casefold()
        candidates=[Path(x) for x in entry.get('_images',{}).get(basename,[]) if Path(x).is_file()]
        candidates=list(dict.fromkeys(candidates))
        if len(candidates)==1:return candidates[0],None
        return None,('Ambiguous map; review required: ' if candidates else 'Missing map: ')+old
    if entry.get('custom'):
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
    normalized = old.replace('\\', '/')
    basename = normalized.rsplit('/', 1)[-1].casefold()
    candidates = [Path(p) for p in catalog_data()['collections'][entry['collection']]['images'].get(basename, [])]
    candidates = [p for p in candidates if p.is_file()]
    original = Path(old)
    # Prefer the precise existing path only if it belongs to this collection.
    try:
        original.resolve().relative_to(Path(entry['collection_root']).resolve())
        if original.is_file():
            return original, None
    except ValueError:
        pass
    if len(candidates) == 1:
        return candidates[0], None
    if candidates:
        signatures = {digest(str(p), p.stat().st_size, p.stat().st_mtime_ns) for p in candidates}
        if len(signatures) == 1:
            # Identical bytes in several folders are safe to resolve by locality.
            model_parts = Path(entry['path']).parts
            def proximity(p):
                return sum(1 for a,b in zip(model_parts,p.parts) if a.casefold()==b.casefold())
            return max(candidates, key=proximity), None
        return None, 'Ambiguous map: %s (%d different candidates)' % (old, len(candidates))
    return None, 'Missing map in collection: ' + old

def repair_material(material, entry):
    if material is None:
        raise RuntimeError('The selected plant has no material.')
    fixed, warnings, classes = [], [], []
    for obj in material_tree(material):
        cls = str(rt.classOf(obj))
        classes.append(cls.casefold())
        if 'missing' in cls.casefold():
            raise RuntimeError('Required material/map plugin is missing: ' + cls)
        for prop in ('filename', 'fileName'):
            if not rt.isProperty(obj, prop):
                continue
            old = str(rt.getProperty(obj, prop) or '')
            if old:
                target, error = resolve_texture(old, entry)
                if target:
                    rt.setProperty(obj, prop, str(target))
                    fixed.append(str(target))
                else:
                    warnings.append(error)
            break
    if entry.get('renderer_policy','corona') == 'corona' and not any(c.startswith('corona') for c in classes):
        raise RuntimeError('Corona material could not be verified for this plant; insertion cancelled.')
    if entry.get('renderer_policy','corona') == 'corona' and any(c.startswith('vraymtl') for c in classes):
        raise RuntimeError('Mixed V-Ray material found; insertion cancelled for review.')
    return fixed, warnings

class Importer:
    def __init__(self):
        self.sources = {}

    def insert(self, entry, renderer='corona', mode='Copy', variant_index=0):
        if renderer != 'corona':
            raise RuntimeError('This catalog inserts Corona assets only.')
        path = Path(entry['path'])
        if not path.is_file():
            raise RuntimeError('MAX file not found: ' + str(path))
        if not entry.get('variants'):
            raise RuntimeError('Choose the exact block in Review matches before insertion.')
        variant = entry['variants'][variant_index]
        object_name = variant['object']
        key = (str(path.resolve()).casefold(), object_name, entry.get('id',''),entry.get('revision',''))
        source = self.sources.get(key)
        before = list(rt.objects)
        previous_selection = list(rt.selection)
        try:
            if source is not None and rt.isValidNode(source):
                node = rt.instance(source) if mode == 'Instance' else rt.copy(source)
                node.material = rt.copy(source.material)
            else:
                names = [str(n) for n in rt.getMAXFileObjectNames(str(path))]
                if object_name not in names:
                    raise RuntimeError('Indexed object is absent: ' + object_name)
                result = rt.mergeMAXFile(str(path), rt.Array(object_name),
                    rt.Name('select'), rt.Name('autoRenameDups'), rt.Name('renameMtlDups'),
                    quiet=True)
                created = [n for n in rt.objects if n not in before]
                if not result or len(created) != 1:
                    raise RuntimeError('Expected one plant object; merge created %d nodes. Rolled back.' % len(created))
                node = created[0]
                if str(rt.superClassOf(node)).casefold() != 'geometryclass':
                    raise RuntimeError('The selected object is not plant geometry.')
                if 'missing' in str(rt.classOf(node)).casefold():
                    raise RuntimeError('The plant geometry requires a missing plugin.')
                libpath = None if entry.get('manual_material') else variant.get('matlib')
                if libpath:
                    if not Path(libpath).is_file():
                        raise RuntimeError('Material library not found: ' + libpath)
                    library = rt.loadTempMaterialLibrary(libpath)
                    # Exact indexed name, or actual imported material name when index omits it.
                    name = variant.get('material') or (str(node.material.name) if node.material else '')
                    matches = [m for m in library if str(m.name) == name]
                    if len(matches) != 1:
                        raise RuntimeError('Cannot uniquely resolve Corona material "%s" in %s' % (name, libpath))
                    node.material = rt.copy(matches[0])
                elif node.material:
                    node.material = rt.copy(node.material)
            if entry.get('managed'):
                import asset_materials
                asset_materials.assign(node,entry)
            fixed, warnings = repair_material(node.material, entry)
            if warnings:
                raise RuntimeError('Unresolved textures; insertion rolled back:\n' + '\n'.join(sorted(set(warnings))))
            node.name = rt.uniqueName(object_name + '_')
            node.position = rt.Point3(0, 0, 0)
            rt.select(node)
            self.sources[key] = node
            rt.redrawViews()
            return node, fixed, warnings
        except Exception:
            for n in list(rt.objects):
                if n not in before and rt.isValidNode(n):
                    rt.delete(n)
            rt.select(rt.Array(*[n for n in previous_selection if rt.isValidNode(n)]))
            raise

def preview_pixmap(path, size):
    if not path or not Path(path).is_file():
        return QtGui.QPixmap()
    reader = QtGui.QImageReader(str(path))
    original = reader.size()
    if original.isValid():
        reader.setScaledSize(original.scaled(size, QtCore.Qt.KeepAspectRatio))
    return QtGui.QPixmap.fromImage(reader.read())

class Browser(QtWidgets.QDialog):
    def __init__(self, parent=None):
        super().__init__(parent)
        self.setWindowTitle(TITLE)
        self.resize(1120, 790)
        self.importer = Importer()
        self.entries, errors = catalog()
        self.scan_job = None
        self.filtered = []
        self.page = 0
        self.page_size = 12
        layout = QtWidgets.QVBoxLayout(self)
        header = QtWidgets.QHBoxLayout()
        self.logo = QtWidgets.QLabel()
        logo = preview_pixmap(PROJECT / 'academy_logo.png', QtCore.QSize(150, 85))
        if logo.isNull():
            self.logo.setText('Academy logo file unavailable')
        else:
            self.logo.setPixmap(logo)
        header.addWidget(self.logo)
        title = QtWidgets.QLabel(TITLE)
        font = title.font()
        font.setPointSize(18)
        title.setFont(font)
        header.addWidget(title, 1)
        header.addWidget(QtWidgets.QLabel('Corona | 3ds Max 2027'))
        layout.addLayout(header)
        import asset_ui
        self.build_label=QtWidgets.QLabel(asset_ui.BUILD)
        self.build_label.setObjectName('libraryBuildLabel')
        layout.addWidget(self.build_label)
        library_actions=QtWidgets.QHBoxLayout()
        for label,action in [('Create Library','create'),('Add Single Plant','single'),('Libraries / Single Assets',None)]:
            button=QtWidgets.QPushButton(label)
            button.clicked.connect(lambda checked=False,a=action:asset_ui.open_manager(self,a))
            library_actions.addWidget(button)
        layout.addLayout(library_actions)
        top = QtWidgets.QHBoxLayout()
        self.collection = QtWidgets.QComboBox()
        self.collection.addItem('All collections', None)
        for cid,meta in catalog_data()['collections'].items():
            self.collection.addItem(meta.get('name',cid),cid)
        self.species = QtWidgets.QComboBox()
        self.species.addItems(['All species'] + sorted({e['species'] for e in self.entries}))
        self.search = QtWidgets.QLineEdit()
        self.search.setPlaceholderText('Search collection, plant name or variant...')
        for w in (self.collection, self.species, self.search):
            top.addWidget(w)
        layout.addLayout(top)
        management = QtWidgets.QHBoxLayout()
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
        body = QtWidgets.QHBoxLayout()
        self.grid = QtWidgets.QListWidget()
        self.grid.setViewMode(QtWidgets.QListView.IconMode)
        self.grid.setResizeMode(QtWidgets.QListView.Adjust)
        self.grid.setMovement(QtWidgets.QListView.Static)
        self.grid.setIconSize(QtCore.QSize(125, 125))
        self.grid.setGridSize(QtCore.QSize(195, 190))
        self.grid.setWordWrap(True)
        body.addWidget(self.grid, 3)
        right = QtWidgets.QVBoxLayout()
        self.preview = QtWidgets.QLabel('Select a plant')
        self.preview.setMinimumSize(310, 310)
        self.preview.setAlignment(QtCore.Qt.AlignCenter)
        right.addWidget(self.preview)
        self.details = QtWidgets.QLabel()
        self.details.setWordWrap(True)
        self.details.setTextInteractionFlags(QtCore.Qt.TextSelectableByMouse)
        right.addWidget(self.details)
        self.variant = QtWidgets.QComboBox()
        self.variant.setMinimumContentsLength(25)
        self.variant.setSizeAdjustPolicy(QtWidgets.QComboBox.AdjustToMinimumContentsLengthWithIcon)
        right.addWidget(QtWidgets.QLabel('Plant variant within selected MAX file'))
        right.addWidget(self.variant)
        right.addStretch()
        body.addLayout(right, 2)
        layout.addLayout(body, 1)
        pages = QtWidgets.QHBoxLayout()
        self.prev = QtWidgets.QPushButton('Previous')
        self.next = QtWidgets.QPushButton('Next')
        self.count = QtWidgets.QLabel()
        for w in (self.prev, self.count, self.next):
            pages.addWidget(w)
        layout.addLayout(pages)
        options = QtWidgets.QHBoxLayout()
        self.mode = QtWidgets.QComboBox()
        self.mode.addItems(['Copy', 'Instance'])
        self.insert_button = QtWidgets.QPushButton('Insert selected plant at origin')
        options.addWidget(self.mode)
        options.addWidget(self.insert_button, 1)
        layout.addLayout(options)
        self.status = QtWidgets.QLabel('\n'.join(errors) if errors else 'Ready. MAX models load only when inserted.')
        self.status.setWordWrap(True)
        layout.addWidget(self.status)
        self.search.textChanged.connect(self.filter_entries)
        self.collection.currentTextChanged.connect(self.filter_entries)
        self.species.currentTextChanged.connect(self.filter_entries)
        self.prev.clicked.connect(lambda: self.change_page(-1))
        self.next.clicked.connect(lambda: self.change_page(1))
        self.grid.currentItemChanged.connect(self.show_preview)
        self.variant.currentIndexChanged.connect(self.update_variant)
        self.insert_button.clicked.connect(self.insert_selected)
        self.filter_entries()

    def filter_entries(self, *_):
        query = self.search.text().strip().casefold()
        species, collection = self.species.currentText(), self.collection.currentData()
        self.filtered = [e for e in self.entries if
            (collection is None or collection in e.get('memberships',[e['collection']])) and
            (species == 'All species' or e['species'] == species) and
            query in (e['name']+' '+e['label']+' '+e.get('collection_label',e['collection'])+' '+' '.join(v['name'] for v in e['variants'])).casefold()]
        custom = bool(collection and catalog_data()['collections'][collection].get('custom'))
        for button in (self.rename_button,self.rescan_button,self.remove_button): button.setEnabled(custom and not self.scan_job)
        self.create_button.setEnabled(not self.scan_job)
        self.cancel_scan_button.setEnabled(bool(self.scan_job))
        self.page = 0
        self.populate()

    def change_page(self, delta):
        self.page = max(0, min(self.page+delta, max(0, (len(self.filtered)-1)//self.page_size)))
        self.populate()

    def populate(self):
        self.grid.clear()
        self.preview.clear()
        self.details.clear()
        self.variant.clear()
        for e in self.filtered[self.page*12:(self.page+1)*12]:
            e = dict(collection_store.entry_context(e,self.collection.currentData()))
            e['path'] = Path(e['path'])
            label=catalog_data()['collections'][self.collection.currentData() or e['collection']].get('name',e['collection'])
            item = QtWidgets.QListWidgetItem(e['label']+'\n'+label)
            item.setData(QtCore.Qt.UserRole, e)
            item.setToolTip(str(e['path']))
            pix = preview_pixmap(e['image'], QtCore.QSize(125,125))
            if not pix.isNull():
                item.setIcon(QtGui.QIcon(pix))
            else:
                item.setText(item.text()+'\n[Preview unavailable]')
            self.grid.addItem(item)
        self.prev.setEnabled(self.page > 0)
        self.next.setEnabled((self.page+1)*12 < len(self.filtered))
        self.count.setText('%d models | Page %d / %d' % (len(self.filtered), self.page+1, max(1, (len(self.filtered)+11)//12)))
        self.insert_button.setEnabled(self.grid.count() > 0 and not self.scan_job)
        self.review_button.setEnabled(False)
        if self.grid.count():
            self.grid.setCurrentRow(0)

    def show_preview(self, item, *_):
        if item is None:
            return
        e = item.data(QtCore.Qt.UserRole)
        self.variant.blockSignals(True)
        self.variant.clear()
        self.variant.addItems([v['name'] for v in e['variants']])
        self.variant.blockSignals(False)
        issues=e.get('issues',[])
        label=catalog_data()['collections'][self.collection.currentData() or e['collection']].get('name',e['collection'])
        self.details.setText(label+'\n'+str(e['path'])+'\n'+str(len(e['variants']))+' plant variant(s)\n'+('\n'.join(issues[:4]) if issues else 'Links available')+'\nMaterials: '+e.get('renderer_policy','corona'))
        self.details.setToolTip('\n'.join(issues))
        self.review_button.setEnabled(bool(e.get('custom')))
        self.insert_button.setEnabled(bool(e['variants']) and not self.scan_job)
        self.update_variant()

    def update_variant(self, *_):
        item = self.grid.currentItem()
        if item is None:
            return
        if self.variant.currentIndex() < 0:
            self.preview.setText('Block not identified. Use Review matches.')
            return
        e = item.data(QtCore.Qt.UserRole)
        v = e['variants'][self.variant.currentIndex()]
        image = v.get('image') or e.get('image')
        pix = preview_pixmap(image, QtCore.QSize(350,350))
        if pix.isNull():
            self.preview.setText('Preview unavailable for this variant.')
        else:
            self.preview.setPixmap(pix)
        self.preview.setToolTip(str(image or 'No matching image'))

    def insert_selected(self):
        item = self.grid.currentItem()
        if item is None:
            return
        self.insert_button.setEnabled(False)
        self.status.setText('Loading selected plant...')
        self.status.repaint()
        try:
            # mergeMAXFile owns its native merge transaction; do not nest it in
            # a pymxs undo block (the previous wrapper crashed Max 2027 on Undo).
            node, fixed, warnings = self.importer.insert(item.data(QtCore.Qt.UserRole),
                mode=self.mode.currentText(), variant_index=self.variant.currentIndex())
            self.status.setText('Inserted %s | %d map references repaired.' % (node.name, len(fixed)))
        except Exception as exc:
            self.status.setText('Insertion failed: ' + str(exc))
            QtWidgets.QMessageBox.warning(self, TITLE, str(exc))
            print(traceback.format_exc())
        finally:
            self.insert_button.setEnabled(True)

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
        if old: old.retire()

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


def show():
    import builtins, qtmax
    old = getattr(builtins, '_maxtree_browser', None)
    if old:
        try:
            old.close()
            old.deleteLater()
        except RuntimeError:
            pass
    window = Browser(qtmax.GetQMaxMainWindow())
    qtmax.DisableMaxAcceleratorsOnFocus(window, True)
    builtins._maxtree_browser = window
    window.show()
    return window

if __name__ == '__main__':
    try:
        show()
    except Exception as exc:
        QtWidgets.QMessageBox.warning(None, TITLE, str(exc))

