# General Blocks Update 3.0

Visible title: **مكتبة المكتب الفني للبلوكات**. This extends the existing project; it does not replace the codebase or create another project.

## Changes and operation
- General Block / Category / Variant wording and search. The academy logo and Plants category remain.
- Retained structured root and manual one-block/three-folder workflows, reviewed scan reports, lazy previews and the existing optional native MAT workflow.
- Explicit object selection for multi-object MAX files and an explicit Full block / all objects option. Tested Copy/Instance cloning preserves group hierarchy and relative placement.
- Origin or the pivot of one preselected scene object. Interactive click-to-place was not added.
- Library map folders and per-block overrides; temporary Max search paths restored after insertion or failure. Only referenced bitmap filename properties are repaired. Unique matches are accepted; missing or ambiguous files cancel insertion and expose candidates for review.
- Scoped rollback deletes only nodes created by the attempt and restores the prior selection. Materials are copied on imported nodes before modification; native merge uses renameMtlDups to protect unrelated materials.
- Right-click Rename, Move to Library, Edit Paths, Review Matches and Remove from Library. Moves preserve map references and reject duplicate full paths in the destination. Catalog actions use overlays instead of rewriting catalog.json.
- Invalid individual records are reported/skipped without preventing other assets from loading.

## Exact production files
Modified (each backed up before editing):
- maxtree_library.py
- asset_ui.py
- asset_store.py
- collection_store.py
- collection_scan.py
- collection_ui.py
- library_bootstrap.py
- README.txt

Added runtime modules:
- block_runtime.py
- block_actions.py
- block_catalog.py

Added validation/report helpers:
- general_validation_host.py
- general_validation_host.ms
- general_make_fixtures.py
- test_general_blocks_ui.py
- test_general_post.py
- test_general_undo.py
- GENERAL_BLOCKS_UPDATE.md
- settings/general_block_validation_20261008/: isolated settings copies, generated test fixtures, scan jobs/logs, JSON evidence and a controls-only screenshot.

Launch.ms and asset_materials.py were not changed. No existing project file or settings file was deleted. The generated test fixtures are new test data, not copies of source-library assets.

## Launcher, settings and backup
Run E:\max lib\Maxtree_Library_Tool\Launch.ms through Scripting > Run Script.
Loading chain: Launch.ms -> library_bootstrap.py -> exact modules beside the launcher (including block_runtime, block_actions and block_catalog) -> maxtree_library.show(). The final visible launch displayed General Blocks Update | 3.0 and the requested Arabic title.

Existing schema-1 settings remain at settings/user_collections.json and settings/asset_libraries.json. New category/map/review fields have backward-compatible defaults; there is no destructive schema replacement. settings/block_overrides.json is a new schema-1 overlay created only on relevant user actions. It stores library/path-keyed metadata changes, hidden references, added references and per-library map folders. settings/history retains previous settings snapshots. Files contain metadata and local paths, not embedded asset contents.

Backup: backup_before_general_blocks_20261008_095330.

The validation Max process used an explicitly scoped test-settings directory: settings/general_block_validation_20261008/state. This did not modify live settings. The test environment variable was removed before the last read-only launch with production settings.

## Actual visible Max 2027 tests
Tests ran in dedicated visible Max 2027 process 26756 through the exact launcher. Actual Qt dialogs, menu actions and insertion buttons were exercised programmatically in Max. Background metadata scans ran in separate empty workers. No user's working scene was saved or reset; only the isolated test scene was reset. Original source MAX files were read for metadata and merged into the isolated scene, never opened for editing or saved.

Passed:
- Requested title, unchanged logo, build label and existing data available. The recorded production-settings launch displayed 965 entries and an empty unsaved scene.
- Structured generic scan of 5 generated MAX fixtures: 5 previews matched; missing and ambiguous texture references were reported before confirmation.
- Manual three-folder scan of the same 5 fixtures, and a separately added single block.
- One clear texture match; a genuinely missing texture; two different files with the same Shared.png filename. Missing/ambiguous cases rolled back insertion. Explicit map review then allowed the duplicate-name asset to insert.
- Single-object Copy, Instance and independent Copy; full group Copy and Instance; one selected object from a multi-object MAX. Relative group separation was preserved.
- Origin placement and placement at an existing object's pivot.
- Pre-existing scene objects/materials and map-search paths preserved on success and failure.
- Actual right-click Rename, Move to Library, duplicate-move rejection, Edit Paths (MAX/maps/preview), Review Matches (objects/maps), and Remove from Library. Library removal and catalog alias rename also passed without source deletion.
- A malformed asset record did not prevent valid records from loading.
- Final-build category/library/variant searching, material-less block insertion, and a real Maxtree Vol.67 Carica insertion through the main browser. The real plant resolved 37 map properties, with zero missing or ambiguous maps, and its source fingerprint stayed unchanged.

Tests found and led to fixes for the Max cloning API location (maxOps.cloneNodes), retaining selection after context edits, and a missing map-button hookup caught on reload. Final successful outcomes are recorded; initial failures are not represented as first-run passes.

## Undo: tested but not reliably supported
| Isolated case | Result | Expected nodes after Undo | Actual nodes |
| --- | --- | --- | --- |
| Single-object merge | Failed to remove insertion | 1 | 2 |
| Multi-object/group merge | Failed to remove insertion | 1 | 4 |
| Copy clone | Passed for this fixture | 2 | 2 |
| Instance clone | Failed to remove insertion | 2 | 3 |

Pre-existing test nodes remained present. Max did not crash in these four tests. Complete-insertion Undo is nevertheless NOT claimed as supported. Both UI windows display this limitation. Imported roots remain selected for explicit deletion.

## Integrity and concurrent settings additions
During the operation tests, fixture/source fingerprints and the then-current production-settings hashes remained unchanged. The recorded production launch also passed those checks.
A later final audit detected a new Maxtree - Plant Models Vol.119 collection and a corresponding 111-entry managed library added to live settings after the test run. These additions were preserved, not reverted. Record-by-record comparison against the pre-change backup found no removed or modified original collection/library records. catalog.json and academy_logo.png still match their pre-change hashes. Therefore the final live settings files are not claimed to be byte-identical to the backup: they correctly retain the newer additions.

## Evidence and remaining manual checks
Reports under settings/general_block_validation_20261008:
- general_results.json: visible workflows, insertions, failures and context menus.
- post_results.json: final-build searches, material-less block and real Maxtree insertion.
- search_path_probe.json: temporary map-search scope.
- undo_results.json: the four native Undo outcomes.
- production_launch.json: title/logo/data/read-only production-settings launch.
- integrity_final.json: unchanged original records and preserved newer Vol.119 additions.
- general_controls.png: visible title, logo and controls without source-preview content.

Not exhaustively tested: every renderer/vendor layout, deeply nested rigs, XRefs, plugin-specific non-bitmap asset properties, all cross-catalog move combinations, or very large-library performance. Test those assets in an empty scene with their required plugins. The newly added Vol.119 collection was preserved but not included in this validation run. Optional native MAT assignment was retained but not separately revalidated with new MAT fixtures in this update. Origin and selected-pivot placement are tested; click-to-place is not provided. Undo remains the explicit unsupported limitation above.
