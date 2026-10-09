"""Maxtree browser for 3ds Max 2027. Sources are read-only."""
from pathlib import Path
import xml.etree.ElementTree as ET
import traceback
from pymxs import runtime as rt
from PySide6 import QtCore, QtGui, QtWidgets

ROOT = Path(r"E:\max lib\maxtree\MT Plants model vol.67\MT Plants model vol.67\MT Plants model vol\MT_PM_V67_Forest_Pack_Mesh_Corona\MT_PM_V67")
MAPS = Path(r"E:\max lib\maxtree\MT Plants model vol.67\MT Plants model vol.67\Maxtree - Plant Models Vol. 67_Maps\Maxtree - Plant Models Vol. 67_Maps")

def relative_path(text):
    return ROOT / text.replace('\\', '/')

def catalog():
    entries, errors = [], []
    for node in ET.parse(ROOT / 'index.xml').getroot().findall('item'):
        try:
            e = {key: (node.findtext(key) or '').strip() for key in
                 ('name', 'maxfile', 'object', 'bitmap', 'matlib', 'material')}
            if not all(e.values()):
                raise ValueError('Incomplete index entry: ' + repr(e))
            short = e['name'].removeprefix('MT_PM_V67_')
            e['species'] = short.rsplit('_', 2)[0].replace('_', ' ')
            e['label'] = e['species'] + ' | ' + '_'.join(short.rsplit('_', 2)[1:])
            e['path'] = relative_path(e['maxfile'])
            thumb = ROOT / 'thumbnails' / Path(e['bitmap']).name
            e['image'] = thumb if thumb.is_file() else relative_path(e['bitmap'])
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

def repair_material(material):
    fixed, warnings = [], []
    for obj in material_tree(material):
        cls = str(rt.classOf(obj))
        if 'missing' in cls.lower():
            raise RuntimeError('Required material/map plugin is missing: ' + cls)
        for prop in ('filename', 'fileName'):
            if not rt.isProperty(obj, prop):
                continue
            old = str(rt.getProperty(obj, prop) or '')
            if not old:
                break
            target = MAPS / old.replace('\\', '/').rsplit('/', 1)[-1]
            if target.is_file():
                rt.setProperty(obj, prop, str(target))
                fixed.append(str(target))
            elif not Path(old).is_file():
                warnings.append('Missing map: ' + old)
            break
    return fixed, warnings

class Importer:
    def __init__(self):
        # Live node references; invalid references are discarded after reset/deletion.
        self.sources = {}

    def insert(self, entry, renderer='corona', mode='Copy'):
        path = entry['path']
        if not path.is_file():
            raise RuntimeError('MAX file not found: ' + str(path))
        libpath = relative_path(entry['matlib'].replace('$(MTRENDERER)', renderer))
        if not libpath.is_file():
            raise RuntimeError('Material library not found: ' + str(libpath))
        key = (str(path), entry['object'], renderer)
        source = self.sources.get(key)
        before = list(rt.objects)
        previous_selection = list(rt.selection)
        try:
            if source is not None and rt.isValidNode(source):
                node = rt.instance(source) if mode == 'Instance' else rt.copy(source)
                # Copy geometry is independent; material is also made independent.
                node.material = rt.copy(source.material)
                fixed, warnings = repair_material(node.material)
            else:
                names = [str(n) for n in rt.getMAXFileObjectNames(str(path))]
                if entry['object'] not in names:
                    raise RuntimeError('Indexed object is absent: ' + entry['object'])
                library = rt.loadTempMaterialLibrary(str(libpath))
                matches = [m for m in library if str(m.name) == entry['material']]
                if len(matches) != 1:
                    raise RuntimeError('Expected one material named ' + entry['material'] + '; found ' + str(len(matches)))
                mat = rt.copy(matches[0])
                fixed, warnings = repair_material(mat)
                # Explicit name list, no scene load, no renderer/environment import.
                result = rt.mergeMAXFile(str(path), rt.Array(entry['object']),
                    rt.Name('select'), rt.Name('autoRenameDups'), rt.Name('useMergedMtlDups'),
                    quiet=True)
                created = [n for n in rt.objects if n not in before]
                if not result or len(created) != 1:
                    raise RuntimeError('Merge did not return exactly one indexed object (got %d).' % len(created))
                node = created[0]
                node.material = mat
            node.name = rt.uniqueName(entry['object'] + '_')
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

class Browser(QtWidgets.QDialog):
    def __init__(self, parent=None):
        super().__init__(parent)
        self.setWindowTitle('Maxtree Vol.67 | Plant Library')
        self.resize(1000, 720)
        self.importer = Importer()
        self.entries, errors = catalog()
        self.filtered = []
        self.page = 0
        self.page_size = 12
        layout = QtWidgets.QVBoxLayout(self)
        top = QtWidgets.QHBoxLayout()
        self.species = QtWidgets.QComboBox()
        self.species.addItems(['All species'] + sorted({e['species'] for e in self.entries}))
        self.search = QtWidgets.QLineEdit()
        self.search.setPlaceholderText('Search plant name or variant...')
        top.addWidget(self.species)
        top.addWidget(self.search)
        layout.addLayout(top)
        body = QtWidgets.QHBoxLayout()
        self.grid = QtWidgets.QListWidget()
        self.grid.setViewMode(QtWidgets.QListView.IconMode)
        self.grid.setResizeMode(QtWidgets.QListView.Adjust)
        self.grid.setMovement(QtWidgets.QListView.Static)
        self.grid.setIconSize(QtCore.QSize(130, 130))
        self.grid.setGridSize(QtCore.QSize(180, 175))
        self.grid.setWordWrap(True)
        body.addWidget(self.grid, 3)
        self.preview = QtWidgets.QLabel('Select a plant')
        self.preview.setMinimumSize(300, 300)
        self.preview.setAlignment(QtCore.Qt.AlignCenter)
        body.addWidget(self.preview, 2)
        layout.addLayout(body, 1)
        pages = QtWidgets.QHBoxLayout()
        self.prev = QtWidgets.QPushButton('Previous')
        self.next = QtWidgets.QPushButton('Next')
        self.count = QtWidgets.QLabel()
        for w in (self.prev, self.count, self.next):
            pages.addWidget(w)
        layout.addLayout(pages)
        options = QtWidgets.QHBoxLayout()
        self.renderer = QtWidgets.QComboBox()
        self.renderer.addItems(['Corona', 'V-Ray', 'Standard'])
        self.mode = QtWidgets.QComboBox()
        self.mode.addItems(['Copy', 'Instance'])
        self.insert_button = QtWidgets.QPushButton('Insert selected at origin')
        for w in (self.renderer, self.mode, self.insert_button):
            options.addWidget(w)
        layout.addLayout(options)
        self.status = QtWidgets.QLabel('\n'.join(errors) if errors else 'Ready. Models load only when inserted.')
        self.status.setWordWrap(True)
        layout.addWidget(self.status)
        self.search.textChanged.connect(self.filter_entries)
        self.species.currentTextChanged.connect(self.filter_entries)
        self.prev.clicked.connect(lambda: self.change_page(-1))
        self.next.clicked.connect(lambda: self.change_page(1))
        self.grid.currentItemChanged.connect(self.show_preview)
        self.insert_button.clicked.connect(self.insert_selected)
        self.filter_entries()

    def filter_entries(self, *_):
        query = self.search.text().strip().casefold()
        species = self.species.currentText()
        self.filtered = [e for e in self.entries if
            (species == 'All species' or e['species'] == species) and
            (query in e['name'].casefold() or query in e['label'].casefold())]
        self.page = 0
        self.populate()

    def change_page(self, delta):
        self.page += delta
        self.populate()

    def populate(self):
        self.grid.clear()
        self.preview.clear()
        for e in self.filtered[self.page*12:(self.page+1)*12]:
            item = QtWidgets.QListWidgetItem(e['label'])
            item.setData(QtCore.Qt.UserRole, e)
            if e['image'].is_file():
                item.setIcon(QtGui.QIcon(str(e['image'])))
            else:
                item.setText(e['label'] + '\n[Missing preview]')
            self.grid.addItem(item)
        self.prev.setEnabled(self.page > 0)
        self.next.setEnabled((self.page+1)*12 < len(self.filtered))
        self.count.setText('%d plants | Page %d / %d' % (len(self.filtered), self.page+1, max(1, (len(self.filtered)+11)//12)))
        self.insert_button.setEnabled(self.grid.count() > 0)
        if self.grid.count():
            self.grid.setCurrentRow(0)

    def show_preview(self, item, *_):
        if item is None:
            return
        entry = item.data(QtCore.Qt.UserRole)
        pix = QtGui.QPixmap(str(entry['image']))
        if pix.isNull():
            self.preview.setText('Preview missing or unreadable:\n' + str(entry['image']))
        else:
            self.preview.setPixmap(pix.scaled(350, 350, QtCore.Qt.KeepAspectRatio, QtCore.Qt.SmoothTransformation))

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
                    ['corona', 'vray', 'standard'][self.renderer.currentIndex()], self.mode.currentText())
            self.status.setText('Inserted %s | %d map references repaired.' % (node.name, len(fixed)))
            if warnings:
                QtWidgets.QMessageBox.warning(self, 'Plant inserted: missing maps', '\n'.join(sorted(set(warnings))))
        except Exception as exc:
            self.status.setText('Insertion failed: ' + str(exc))
            QtWidgets.QMessageBox.warning(self, 'Maxtree Library', str(exc))
            print(traceback.format_exc())
        finally:
            self.insert_button.setEnabled(True)

def show():
    import builtins
    old = getattr(builtins, '_maxtree_browser', None)
    if old:
        old.close()
        old.deleteLater()
    try:
        import qtmax
        parent = qtmax.GetQMaxMainWindow()
        window = Browser(parent)
        qtmax.DisableMaxAcceleratorsOnFocus(window, True)
    except ImportError:
        window = Browser()
    builtins._maxtree_browser = window
    window.show()
    return window

if __name__ == '__main__':
    try:
        show()
    except Exception as exc:
        QtWidgets.QMessageBox.warning(None, 'Maxtree Library', str(exc))
