????? ?????? ?????? ?????

Run in 3ds Max 2027: Scripting > Run Script > Launch.ms in this folder.
Keep the Python modules beside Launch.ms. The existing academy logo and base catalog are retained.

CREATE YOUR OWN COLLECTION
Choose Create Collection, enter a name, then Add folder one or more times.
Choose Mixed assets, or assign separate Models only, Previews only and Maps only folders.
The scanner reads local MAX metadata in a separate 3ds Max batch process. It does not merge models into your working scene. Startup can take time while installed plugins initialize.
Choose Require Corona materials, or Use embedded source materials (no conversion). Required source-material plugins must be installed already.

BROWSE AND INSERT
Use collection filtering and plant-name search. Previews load on demand.
Select a model/variant, choose Copy or Instance, then Insert.
Only the identified named object is merged. Unclear multi-object scenes require Review matches; the whole scene is never silently merged.
Review matches lets you explicitly choose an object, preview, map candidates and optionally a material library with an exact material name.
Missing or ambiguous references appear in the details. A filename fallback is not proof of a material relationship.

MANAGE CUSTOM COLLECTIONS
Select a custom collection to Rename, Rescan, or Remove collection.
Rescan preserves explicit review choices. Cancel scan retains the previous successful result.
Remove archives only the collection configuration; source files remain untouched.
Existing built-in collections remain preserved in catalog.json.
Duplicate normalized full model paths share one asset with multiple collection memberships.

LOCAL SETTINGS
settings/user_collections.json: collection names, selected folders, review choices and scan-result references.
settings/jobs/: metadata-only scan results, progress, launch helpers and logs.
settings/history/: previous configuration snapshots.
backup_before_custom_20261007_193150/: pre-change code and configuration backup.
Only path references and metadata are stored. Models, maps, previews and material libraries are not copied or packaged.

LIMITATIONS
MAX files are supported; archives, GrowFX definitions and arbitrary formats are not automatically converted.
Unrecognized index formats, inaccessible files, ambiguous object names and missing assets need review.
Scanning does not inspect full material graphs by opening every scene. Material/plugin compatibility and actual map properties are validated at insertion.
Multiple-object assemblies must be reviewed; automatic assembly/group reconstruction is not implemented.
Native Undo after merge was not included in the final integration test.

VALIDATION
See test_custom_results.json and CUSTOM_COLLECTIONS_UPDATE.md.


LIBRARIES 2.0 - STRUCTURED AND MANUAL WORKFLOWS
Run the same Launch.ms. The visible build label is Custom Collections Update | Libraries 2.0.
Launch.ms executes library_bootstrap.py, which loads exact source modules from this folder and explicitly opens Browser.show(). Running it again reloads the code; finish/cancel an active scan first.

Create Library offers:
1. Import a structured asset library: Maxtree (verified Corona variants only), or a generic indexed/filename-based library. Choose the root and renderer. Review the scan counts, previews, maps, exclusions and duplicates before adding confirmed models.
2. Create a manually organized library: Add one block, or Bulk-add from three folders (models, maps/materials, previews). Manual bulk previews match model basenames, ignoring extension. Missing/ambiguous matches remain flagged.

Manual organization note:
Organize your library into separate folders for Blocks/Models, Maps/Textures, and Preview Images. Keep each preview image's filename the same as its corresponding block/model filename (the extension can differ). Keep the map filenames intact so the model's material references can locate them.

Libraries / Single Assets opens the management window. Every entry is displayed only inside its chosen library. Add Single Plant targets the dedicated Single Assets section. Add one block targets the currently selected library.
Use Edit / Replace to change display name or paths. Use Review matches for object/preview/map choices. Rescan lets folder-based libraries change their folders before reviewing new results; individually added entries rescan their selected paths. Rename and Remove library change references only. Remove entry never deletes source files.
The report defaults existing cross-library paths to unchecked; select the explicit duplicate permission to add the same model to another library. A library contains at most one entry per normalized full model path.

Native MAX and MAT files are supported. Without a MAT selection, imported materials are retained. With a MAT selection, unique exact-name matches are applied only to the newly imported material graph. Unmatched or duplicate names open Review material mapping with explicit choices including keeping embedded materials. Material-name collisions during merge use renameMtlDups to protect unrelated scene materials; a renamed imported material may consequently require explicit mapping. Missing textures resolve by their existing paths or unique basenames in the selected map index; unresolved references roll back insertion for review.

NEW SETTINGS (existing collection settings are unchanged)
settings/asset_libraries.json: schema 1; libraries contain IDs, names, scanner configuration, per-entry metadata/local paths, explicit choices and image-path indexes. No model, texture or preview contents are embedded.
settings/history/assets_*.json: prior asset-library configurations.
settings/jobs/: isolated draft scans; canceled/unconfirmed drafts are not active library entries.
backup_before_library_workflows_20261007_213505/: pre-update launcher/code and original collection-settings backup.

Validation details are in LIBRARY_WORKFLOWS_UPDATE.md and settings/ui_validation/workflow_results.json.
