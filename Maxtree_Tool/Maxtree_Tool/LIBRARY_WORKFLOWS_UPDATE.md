# Existing library: workflow and launcher update

The existing project at E:\max lib\Maxtree_Library_Tool was updated in place. The title ????? ?????? ?????? ?????, academy logo, 963 base model paths, 1204 variants and 11 original collections remain intact.

## Run this exact launcher
In 3ds Max: Scripting > Run Script > E:\max lib\Maxtree_Library_Tool\Launch.ms.
Look for **Custom Collections Update | Libraries 2.0**.

Loading chain: Launch.ms -> python.ExecuteFile(library_bootstrap.py) -> exact source execution of collection_store.py, collection_scan.py, collection_ui.py, asset_store.py, asset_materials.py, asset_ui.py and maxtree_library.py -> maxtree_library.show() -> Browser. Create Library / Add Single Plant / Libraries / Single Assets open asset_ui.LibraryManager.

The old launcher only ExecuteFile'd maxtree_library.py and relied on its execution context and cached helper imports. The new bootstrap explicitly reloads the modules from its own folder and calls show. No older references were found in the checked Autodesk user script tree and Max 2027 scripts folder; this is not a claim that every drive was exhaustively searched.

## Visible UI
Create Library offers a structured import (Maxtree Corona, or the generic supported rules) and a manual library (one block, or three separate folders). Every scan opens a review report before any active library is created or updated. Existing cross-library model paths are unchecked until explicitly allowed. Objects, previews and maps can be reviewed. Generic support is limited to native MAX files, exact-basename previews and the supported library/item XML schema; arbitrary vendor layouts are not presumed compatible.

The library manager keeps entries within their selected library. Single Assets is separate. It provides search, lazy previews, Add one block, Bulk-add, Edit / Replace, Review matches, Rescan, Rename, Remove entry and Remove library. Original folder-collection controls remain available in the main browser.

## Files changed
Existing production files modified:
- Launch.ms
- maxtree_library.py
- collection_scan.py
- collection_ui.py
- README.txt

New production files:
- library_bootstrap.py
- asset_store.py
- asset_materials.py
- asset_ui.py

New validation/report files:
- launcher_ui_probe.py and launcher_ui_probe.ms
- test_visible_workflows.py
- test_visible_ambiguity.py
- LIBRARY_WORKFLOWS_UPDATE.md
- settings/ui_validation/*.json and controls-only *.png evidence
- settings/jobs/* draft job launchers, metadata results, progress and logs

New settings: settings/asset_libraries.json and settings/history/assets_*.json snapshots. Existing catalog.json, academy_logo.png, collection_store.py and settings/user_collections.json were not changed by this update.

## Settings format and safety
asset_libraries.json uses schema 1 and a libraries array. Libraries store id, name, config (workflow, type, renderer, selected folders), entries, image-path lookup and removed state. Entries contain normalized full model paths, display names, scanned object names/variants, explicit preview/MAT/maps paths, map references and review choices. Metadata only: no models, previews, textures or MAT contents are embedded or copied.

Deduplication is by normalized full path within each library. Explicitly adding an existing model to a different library is allowed. Rescans merge confirmed metadata and retain explicit choices; they do not delete old source references just because a file disappears. Edit / Replace changes an entry's path. Removal affects active settings only, with configuration history retained.

Pre-change backup: backup_before_library_workflows_20261007_213505. The original collection configuration was backed up but its format and bytes were not modified.

## Actual 3ds Max 2027 verification
Tests ran in a dedicated visible Max 2027 GUI process (PID 61080, version 29.0.0.3590), not just standalone helpers or Max Batch. The exact Launch.ms was executed and re-executed after fixes. Dialog buttons and fields were exercised through Qt, with on-screen captures of controls, scan reports and the mapping dialog. Background metadata scans used isolated Max Batch workers; insertions occurred in the visible clean test session.

PASS:
- Structured Maxtree import: 72 verified Corona model files, 72 linked previews, 2502 resolved metadata map references, zero unresolved map references. Explicit duplicate approval was required because those paths already exist in the original catalog.
- Manual three-folder import: 72 models and 72 exact-basename previews; insertion preserved embedded materials and resolved 37 texture properties.
- Manual named library Pergolas: two distinct real MAX test models with individually selected native MAT paths. Unique exact-name material assignment was verified on the actual imported nodes. These were plant fixtures used to test the freely named library, not a claim that real pergola models were available.
- Add Single Plant: one entry shown only in Single Assets; the two Pergolas entries remained in Pergolas.
- Edit / Replace: display name and MAX path changed to a third real model, followed by successful insertion/material assignment.
- Rescan, library rename, entry removal and library removal passed through visible controls.
- Real ambiguity scan: selecting a preview root containing both full previews and thumbnails produced 72 ambiguous matches and zero automatic preview assignments. An explicit preview/object/MAT choice was reviewed and accepted for one model.
- Real material-name collision: the initial test exposed the legacy useMergedMtlDups behavior replacing an unrelated material. The importer now uses renameMtlDups, verified against a real collision. The explicit material mapping dialog assigned the chosen Corona material to the newly imported node; 37 texture properties resolved and the unrelated material handle/properties stayed unchanged.
- Final exact-launcher check: visible build label and controls, all module paths in the project folder, 963 base entries, empty unsaved scene, original catalog/logo/user_collections.json hashes unchanged. Sample MAX/MAT/preview/map fingerprints also remained unchanged.

The live tests additionally caught and fixed filename search and retained-search filtering that could hide newly added entries. Test libraries were archived/removed from active browsing and Single Assets is empty. The clean GUI session is left open showing the updated browser. No existing project scene was reset or saved over.

Evidence: settings/ui_validation/workflow_results.json, ambiguity_results.json, merge_conflict_probe.json, final_launcher_check.json and launcher_controls.png.

## Limits
Only native MAX and MAT formats supported by the existing importer are handled. Unclear multi-object assemblies require selecting a specific object; whole-scene merging is not used. Missing plugin/material types cannot be converted automatically. Generic vendor structures and V-Ray variants were not tested with independent vendor fixtures. Metadata scanning cannot guarantee all plugin-specific assets; actual material filename properties are checked at insertion. The map-ambiguity branch was not tested with a real pair of duplicate texture filenames; real preview ambiguity and material-name ambiguity were tested. Native Undo and a full regression across all eleven preserved collections were not retested in this update.
