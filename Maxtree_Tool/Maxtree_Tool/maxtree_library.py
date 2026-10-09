"""General-purpose MAX block browser; source libraries remain read-only."""
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
TITLE = 'مكتبة المكتب الفني للبلوكات'

@lru_cache(maxsize=1)
def catalog_data():
    import block_catalog
    return block_catalog.apply(collection_store.effective_catalog())

def catalog():
    entries, errors, seen = [], list(catalog_data().get('errors',[])), set()
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
        raise RuntimeError('The selected block has no material.')
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
        raise RuntimeError('Corona material could not be verified for this block; insertion cancelled.')
    if entry.get('renderer_policy','corona') == 'corona' and any(c.startswith('vraymtl') for c in classes):
        raise RuntimeError('Mixed V-Ray material found; insertion cancelled for review.')
    return fixed, warnings

class Importer:
    def __init__(self):
        self.sources = {}

    def insert(self, entry, renderer='corona', mode='Copy', variant_index=0, placement=None, parent=None):
        import block_runtime
        return block_runtime.insert(self,entry,renderer,mode,variant_index,placement,parent)


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
        header.addWidget(QtWidgets.QLabel('MAX blocks | 3ds Max 2027'))
        
        # Authentication & Account Controls Header
        import auth_ui
        self.login_button = QtWidgets.QPushButton("Login")
        self.login_button.setStyleSheet(
            "QPushButton { background-color: #2e7d32; color: #ffffff; font-weight: bold; padding: 6px 16px; border: 1px solid #4caf50; border-radius: 4px; font-size: 13px; }"
            "QPushButton:hover { background-color: #388e3c; color: #ffffff; }"
            "QPushButton:pressed { background-color: #1b5e20; color: #ffffff; }"
            "QPushButton:focus { outline: 2px solid #81c784; }"
        )
        self.login_button.clicked.connect(self.show_login_dialog)
        header.addWidget(self.login_button)
        
        self.account_widget = auth_ui.HeaderAccountWidget(self)
        self.account_widget.logout_requested.connect(self.perform_logout)
        self.account_widget.revalidate_requested.connect(self.revalidate_license)
        header.addWidget(self.account_widget)
        
        layout.addLayout(header)
        
        self.lock_banner = QtWidgets.QLabel("")
        self.lock_banner.setStyleSheet("color: white; background-color: #d9534f; font-weight: bold; padding: 6px; border-radius: 3px;")
        self.lock_banner.setWordWrap(True)
        self.lock_banner.setVisible(False)
        layout.addWidget(self.lock_banner)

        import asset_ui
        self.build_label=QtWidgets.QLabel(asset_ui.BUILD)
        self.build_label.setObjectName('libraryBuildLabel')
        layout.addWidget(self.build_label)
        library_actions=QtWidgets.QHBoxLayout()
        self.library_action_buttons = []
        for label,action in [('Create Library','create'),('Add Single Block','single'),('Libraries / Single Assets',None)]:
            button=QtWidgets.QPushButton(label)
            button.clicked.connect(lambda checked=False,a=action:asset_ui.open_manager(self,a))
            library_actions.addWidget(button)
            self.library_action_buttons.append(button)
        layout.addLayout(library_actions)
        top = QtWidgets.QHBoxLayout()
        self.collection = QtWidgets.QComboBox()
        self.collection.addItem('All collections', None)
        for cid,meta in catalog_data()['collections'].items():
            self.collection.addItem(meta.get('name',cid),cid)
        self.species = QtWidgets.QComboBox()
        self.species.addItems(['All categories'] + sorted({e.get('category','Plants') for e in self.entries}))
        self.search = QtWidgets.QLineEdit()
        self.search.setPlaceholderText('Search block, category, library or variant...')
        for w in (self.collection, self.species, self.search):
            top.addWidget(w)
        layout.addLayout(top)
        management = QtWidgets.QHBoxLayout()
        self.library_maps_button=QtWidgets.QPushButton('Library maps...')
        self.library_maps_button.clicked.connect(self.set_library_maps)
        management.addWidget(self.library_maps_button)
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
        self.preview = QtWidgets.QLabel('Select a block')
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
        right.addWidget(QtWidgets.QLabel('Variant / object within selected MAX file'))
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
        self.insert_button = QtWidgets.QPushButton('Insert selected block')
        options.addWidget(self.mode)
        options.addWidget(self.insert_button, 1)
        self.placement=QtWidgets.QComboBox();self.placement.addItems(['Origin (0,0,0)','Selected object pivot']);options.insertWidget(1,self.placement)
        layout.addLayout(options)
        self.undo_note=QtWidgets.QLabel('Native Undo does not reliably remove a complete insertion in this build. Inserted blocks remain selected for manual deletion.');self.undo_note.setWordWrap(True);layout.addWidget(self.undo_note)
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
        import block_actions
        block_actions.attach(self.grid,self,lambda:self.grid.currentItem().data(QtCore.Qt.UserRole) if self.grid.currentItem() else None,self.refresh_catalog)

        # License & Access Control Setup
        self.setup_license_manager()

    def setup_license_manager(self):
        import maxtree_license_manager, auth_ui
        self.license_manager = maxtree_license_manager.get_manager()
        self.license_manager.state_changed.connect(self.on_license_state_changed)

        self.revalidate_timer = QtCore.QTimer(self)
        self.revalidate_timer.setInterval(60000)  # Periodic license verification (60s)
        self.revalidate_timer.timeout.connect(self.revalidate_license)
        self.revalidate_timer.start()

        # Check initial authorization state
        self.on_license_state_changed(self.license_manager.state, {
            "authorized": self.license_manager.is_authorized(),
            "error": self.license_manager.last_error
        })
        if self.license_manager.token:
            self.revalidate_license()

    def on_license_state_changed(self, state, meta):
        authorized = meta.get("authorized", False)
        err = meta.get("error", "")
        self.account_widget.update_view()
        if authorized:
            self.apply_unlocked_state()
        else:
            self.apply_locked_state(err or "Authentication required.")

    def show_login_dialog(self):
        import auth_ui
        dialog = auth_ui.LoginDialog(self)
        if dialog.exec() == QtWidgets.QDialog.Accepted:
            self.revalidate_license()

    def revalidate_license(self):
        token = self.license_manager.token
        if not token:
            self.apply_locked_state("Login required to access Maxtree Tool.")
            return

        import maxtree_api_client, auth_ui
        client = maxtree_api_client.MaxtreeApiClient()
        seq = self.license_manager.current_sequence

        self.status_worker = auth_ui.StatusWorker(client, token, seq, parent=self)
        self.status_worker.finished_signal.connect(self.on_status_success)
        self.status_worker.error_signal.connect(self.on_status_error)
        self.status_worker.finished.connect(self.status_worker.deleteLater)
        self.status_worker.start()

    def on_status_success(self, res, seq):
        user = res.get("user", {})
        lic = res.get("license", {})
        self.license_manager.handle_status_success(user, lic, seq)

    def on_status_error(self, err_code, message, seq):
        self.license_manager.handle_error(err_code, message, seq)

    def perform_logout(self):
        token = self.license_manager.token
        self.license_manager.logout(server_revoked=False)
        if token:
            import maxtree_api_client, auth_ui
            client = maxtree_api_client.MaxtreeApiClient()
            seq = self.license_manager.current_sequence
            self.logout_worker = auth_ui.LogoutWorker(client, token, seq, parent=self)
            self.logout_worker.finished.connect(self.logout_worker.deleteLater)
            self.logout_worker.start()

    def apply_locked_state(self, reason=""):
        self.lock_banner.setVisible(True)
        self.lock_banner.setText(f"🔒 [LOCKED] {reason}")
        self.login_button.setVisible(True)

        self.grid.clear()
        self.grid.setEnabled(False)
        self.preview.setText("🔒 Interface locked.\nPlease log in with an active Maxtree account.")
        self.details.clear()
        self.variant.clear()
        self.variant.setEnabled(False)

        self.search.setEnabled(False)
        self.collection.setEnabled(False)
        self.species.setEnabled(False)

        self.library_maps_button.setEnabled(False)
        self.create_button.setEnabled(False)
        self.rename_button.setEnabled(False)
        self.rescan_button.setEnabled(False)
        self.remove_button.setEnabled(False)
        self.cancel_scan_button.setEnabled(False)
        self.review_button.setEnabled(False)

        self.prev.setEnabled(False)
        self.next.setEnabled(False)
        self.count.setText("0 models | Locked")

        self.mode.setEnabled(False)
        self.placement.setEnabled(False)
        self.insert_button.setEnabled(False)

        for button in getattr(self, 'library_action_buttons', []):
            button.setEnabled(False)

    def apply_unlocked_state(self):
        self.lock_banner.setVisible(False)
        self.login_button.setVisible(False)
        self.grid.setEnabled(True)
        self.search.setEnabled(True)
        self.collection.setEnabled(True)
        self.species.setEnabled(True)
        self.mode.setEnabled(True)
        self.placement.setEnabled(True)
        self.variant.setEnabled(True)

        for button in getattr(self, 'library_action_buttons', []):
            button.setEnabled(True)

        self.filter_entries()

    def filter_entries(self, *_):
        query = self.search.text().strip().casefold()
        species, collection = self.species.currentText(), self.collection.currentData()
        contextual=[collection_store.entry_context(e,collection) if collection else e for e in self.entries]
        self.filtered = [e for e in contextual if
            (collection is None or collection in e.get('memberships',[e['collection']])) and
            (species == 'All categories' or e.get('category','Plants') == species) and
            query in (e.get('name','')+' '+e.get('category','Plants')+' '+e['label']+' '+e.get('collection_label',e['collection'])+' '+' '.join(e.get('object_names',[]))+' '+' '.join(v['name'] for v in e['variants'])).casefold()]
        custom = bool(collection and catalog_data()['collections'][collection].get('custom'))
        for button in (self.rename_button,self.rescan_button,self.remove_button): button.setEnabled(custom and not self.scan_job)
        self.create_button.setEnabled(not self.scan_job)
        self.library_maps_button.setEnabled(bool(collection) and not self.scan_job)
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
        import block_actions
        self.details.setText(block_actions.details(e,label))
        self.details.setToolTip('\n'.join(issues))
        self.review_button.setEnabled(True)
        self.insert_button.setEnabled(Path(e['path']).is_file() and not self.scan_job)
        self.update_variant()

    def update_variant(self, *_):
        item = self.grid.currentItem()
        if item is None:
            return
        if self.variant.currentIndex() < 0:
            e=item.data(QtCore.Qt.UserRole)
            pix=preview_pixmap(e.get('image'),QtCore.QSize(350,350))
            if pix.isNull():self.preview.setText('Select objects when inserting this block.')
            else:self.preview.setPixmap(pix)
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
        self.status.setText('Loading selected block...')
        self.status.repaint()
        try:
            # mergeMAXFile owns its native merge transaction; do not nest it in
            # a pymxs undo block (the previous wrapper crashed Max 2027 on Undo).
            e=item.data(QtCore.Qt.UserRole)
            node, fixed, warnings = self.importer.insert(e,
                mode=self.mode.currentText(), variant_index=self.variant.currentIndex(),placement='selection' if self.placement.currentIndex()==1 else None,parent=self)
            self.status.setText('Inserted '+str(node.name)+' | '+__import__('block_runtime').summary_text(e.get('_last_map_report',[])))
        except Exception as exc:
            if 'e' in locals():item.setData(QtCore.Qt.UserRole,e)
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
        self.species.addItems(['All categories']+sorted({e.get('category','Plants') for e in self.entries}))
        self.species.blockSignals(False)
        self.search.clear()
        self.filter_entries()

    def set_library_maps(self):
        cid=self.collection.currentData()
        if not cid:return
        folder=QtWidgets.QFileDialog.getExistingDirectory(self,'Library maps folder')
        if folder:
            import block_catalog
            data=block_catalog.state();data.setdefault('library_maps',{})[cid]=folder;block_catalog.save(data)
            self.refresh_catalog(cid)

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
        if item:
            import block_actions
            block_actions.run(self,'Review Matches',item.data(QtCore.Qt.UserRole),self.refresh_catalog)

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

