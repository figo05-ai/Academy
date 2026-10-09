"""مكتبة نباتات المكتب الفني — Corona catalog; source libraries remain read-only."""
from pathlib import Path
import json, hashlib, traceback
from functools import lru_cache
from pymxs import runtime as rt
from PySide6 import QtCore, QtGui, QtWidgets

PROJECT = Path(__file__).resolve().parent
TITLE = 'مكتبة نباتات المكتب الفني'

@lru_cache(maxsize=1)
def catalog_data():
    data = json.loads((PROJECT / 'catalog.json').read_text(encoding='utf-8'))
    if data.get('schema') != 2:
        raise RuntimeError('Unsupported catalog format. Please rebuild the catalog.')
    return data

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
            if not e['variants']:
                raise ValueError('No verified plant objects: ' + e['name'])
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
    if not any(c.startswith('corona') for c in classes):
        raise RuntimeError('Corona material could not be verified for this plant; insertion cancelled.')
    if any(c.startswith('vraymtl') for c in classes):
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
        variant = entry['variants'][variant_index]
        object_name = variant['object']
        key = (str(path.resolve()).casefold(), object_name)
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
                    rt.Name('select'), rt.Name('autoRenameDups'), rt.Name('useMergedMtlDups'),
                    quiet=True)
                created = [n for n in rt.objects if n not in before]
                if not result or len(created) != 1:
                    raise RuntimeError('Expected one plant object; merge created %d nodes. Rolled back.' % len(created))
                node = created[0]
                if str(rt.superClassOf(node)).casefold() != 'geometryclass':
                    raise RuntimeError('The selected object is not plant geometry.')
                if 'missing' in str(rt.classOf(node)).casefold():
                    raise RuntimeError('The plant geometry requires a missing plugin.')
                libpath = variant.get('matlib')
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
        top = QtWidgets.QHBoxLayout()
        self.collection = QtWidgets.QComboBox()
        self.collection.addItems(['All collections'] + sorted({e['collection'] for e in self.entries}))
        self.species = QtWidgets.QComboBox()
        self.species.addItems(['All species'] + sorted({e['species'] for e in self.entries}))
        self.search = QtWidgets.QLineEdit()
        self.search.setPlaceholderText('Search collection, plant name or variant...')
        for w in (self.collection, self.species, self.search):
            top.addWidget(w)
        layout.addLayout(top)
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
        self.insert_button = QtWidgets.QPushButton('Insert selected Corona plant at origin')
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
        species, collection = self.species.currentText(), self.collection.currentText()
        self.filtered = [e for e in self.entries if
            (collection == 'All collections' or e['collection'] == collection) and
            (species == 'All species' or e['species'] == species) and
            query in (e['name']+' '+e['label']+' '+e['collection']+' '+' '.join(v['name'] for v in e['variants'])).casefold()]
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
            item = QtWidgets.QListWidgetItem(e['label']+'\n'+e['collection'])
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
        self.insert_button.setEnabled(self.grid.count() > 0)
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
        self.details.setText(e['collection']+'\n'+str(e['path'].relative_to(Path(e['collection_root'])))+
            '\n'+str(len(e['variants']))+' plant variant(s)')
        self.update_variant()

    def update_variant(self, *_):
        item = self.grid.currentItem()
        if item is None or self.variant.currentIndex() < 0:
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
            import pymxs
            with pymxs.undo(True, 'Insert Maxtree plant'):
                node, fixed, warnings = self.importer.insert(item.data(QtCore.Qt.UserRole),
                    mode=self.mode.currentText(), variant_index=self.variant.currentIndex())
            self.status.setText('Inserted %s | %d map references repaired.' % (node.name, len(fixed)))
        except Exception as exc:
            self.status.setText('Insertion failed: ' + str(exc))
            QtWidgets.QMessageBox.warning(self, TITLE, str(exc))
            print(traceback.format_exc())
        finally:
            self.insert_button.setEnabled(True)

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

