# Custom local collections update

Updated the existing project; preserved the title ????? ?????? ?????? ?????, academy_logo.png and the original catalog (963 distinct model files, 1204 variants, 11 collections).

## Files
- Modified maxtree_library.py: collection controls, filtering, issue display, explicit review and custom map resolution; removed the unsafe outer Python Undo wrapper around merge/material work.
- Added collection_store.py: versioned configuration overlay, full-path deduplication, configuration history and retained review choices.
- Added collection_scan.py: recursive read-only file/index matching and safe MAX name/asset metadata scanning.
- Added collection_ui.py: collection/review dialogs and asynchronous isolated scan process.
- Updated README.txt; added this report and test_custom_collections.py/.ms with test_custom_results.json and test logs.
- Launch.ms, catalog.json and academy_logo.png remain unchanged.

## Settings and backup
Collection settings are in settings/user_collections.json. Results and logs are in settings/jobs; prior settings are in settings/history. The pre-change backup is backup_before_custom_20261007_193150.
Removing a collection archives its settings without deleting source data. Test collection records are archived. No source asset content was copied into the new configuration or helpers.

## Final validation
The clean 3ds Max 2027 batch integration test returned PASS. It used the user-selected name ????? ?????? ????? and existing Vol.67 paths.
- Created a collection and scanned 72 models in a separate process while the test scene remained empty.
- All 72 entries had preview references; collection filtering returned 72 and name search selected one model. UI widgets and review dialog initialized successfully.
- Target model had 34 resolved metadata map references; insertion verified 37 existing filename properties on its material tree.
- Rescan retained explicit review choices; rename and removal passed.
- Actual insert button handler, Copy and Instance passed, including instance relationship checks.
- Global model count remained 963 when the same paths were added as a custom collection.
- Synthetic ambiguous-map and multi-object metadata cases remained unresolved for review.
- Source samples, base catalog and logo fingerprints were unchanged; no project scene was saved.

This was an automated clean batch-session UI/insertion test, not a manual interactive usability test. Interactive event-loop responsiveness was not measured. Two real scan processes completed successfully; results are accepted before plugin shutdown completes. No forced process exit is used by the production scanner.

## Remaining review cases
Unknown XML schemas and unclear object assemblies require explicit review; the scanner does not guess a whole-scene insertion. Missing maps and competing filenames must be selected in Review matches. Material libraries may be assigned with exact material names; no renderer conversion is provided. Source-material mode with non-Corona plugins was not tested. Native Undo after insertion was not retested.

Existing catalog exclusions remain unchanged, including the previously excluded nine Vol.15 models with missing maps. This custom-collection test used Vol.67; it did not repeat insertion testing across all eleven preserved collections.
