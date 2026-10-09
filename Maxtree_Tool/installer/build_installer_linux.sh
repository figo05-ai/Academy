#!/usr/bin/env bash
# Linux Build Script for Maxtree Tool 3ds Max Plugin Installer
# Uses Wine + Portable Inno Setup ISCC compiler to generate genuine Windows PE EXE installer

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
SOURCE_DIR="$ROOT_DIR/Maxtree_Tool/Maxtree_Tool"
DIST_DIR="$ROOT_DIR/dist"
BUNDLE_DIR="$DIST_DIR/staging/Maxtree.bundle"
CONTENTS_DIR="$BUNDLE_DIR/Contents"
SCRATCH_DIR="/home/figo/.gemini/antigravity/brain/c269358b-057b-4eb2-a79a-8584fcc0bfd0/scratch"
WINE_BIN="$SCRATCH_DIR/wine/bin/wine"
ISCC_EXE="$SCRATCH_DIR/inno/ISCC.exe"

echo "========================================================="
echo "Building Maxtree Tool Windows Installer on Linux"
echo "========================================================="

# 1. Prepare Staging Directory
rm -rf "$DIST_DIR"
mkdir -p "$CONTENTS_DIR"

# 2. Whitelisted Distribution Files
WHITELIST=(
    "Launch.ms"
    "library_bootstrap.py"
    "maxtree_api_client.py"
    "maxtree_license_manager.py"
    "auth_ui.py"
    "maxtree_library.py"
    "block_actions.py"
    "block_catalog.py"
    "block_runtime.py"
    "collection_store.py"
    "collection_ui.py"
    "collection_scan.py"
    "asset_store.py"
    "asset_ui.py"
    "asset_materials.py"
    "academy_logo.png"
    "catalog.json"
)

for file in "${WHITELIST[@]}"; do
    if [ -f "$SOURCE_DIR/$file" ]; then
        cp "$SOURCE_DIR/$file" "$CONTENTS_DIR/"
    else
        echo "Error: Required distribution file missing: $file"
        exit 1
    fi
done

# Copy Settings & Launcher
mkdir -p "$CONTENTS_DIR/settings"
if [ -f "$SOURCE_DIR/settings/config.json" ]; then
    cp "$SOURCE_DIR/settings/config.json" "$CONTENTS_DIR/settings/"
fi
cp "$SCRIPT_DIR/Launcher.ms" "$CONTENTS_DIR/"

# Copy Package Manifest & MacroScript
cp "$SCRIPT_DIR/PackageContents.xml" "$BUNDLE_DIR/"
cp "$SCRIPT_DIR/MaxtreeMacro.ms" "$CONTENTS_DIR/"

echo "Distribution bundle staged at: $BUNDLE_DIR"

# 3. Generate Release Manifest via Python
python3 -c "
import json, hashlib
from pathlib import Path

bundle = Path('$BUNDLE_DIR')
dist = Path('$DIST_DIR')

manifest = {
    'version': '3.2.0',
    'series_min': '2025',
    'series_max': '2027',
    'files': []
}

for fpath in bundle.rglob('*'):
    if fpath.is_file():
        rel = fpath.relative_to(bundle).as_posix()
        manifest['files'].append({
            'path': rel,
            'size_bytes': fpath.stat().st_size,
            'sha256': hashlib.sha256(fpath.read_bytes()).hexdigest()
        })

(dist / 'release_manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf-8')
print('Generated release_manifest.json with', len(manifest['files']), 'staged files.')
"

# 4. Compile Inno Setup Installer using Wine
if [ -f "$WINE_BIN" ] && [ -f "$ISCC_EXE" ]; then
    echo "Compiling Inno Setup Script with Wine..."
    WINEDEBUG=-all "$WINE_BIN" "$ISCC_EXE" "Maxtree_Tool/installer/Maxtree_Setup.iss" "/Odist"
    
    EXE_OUTPUT="$DIST_DIR/Maxtree_Setup_3.2.0_x64.exe"
    if [ -f "$EXE_OUTPUT" ]; then
        SIZE=$(stat -c%s "$EXE_OUTPUT")
        SHA=$(sha256sum "$EXE_OUTPUT" | awk '{print $1}')
        echo "========================================================="
        echo "SUCCESS: Windows Installer Built Successfully on Linux!"
        echo "Executable Path: $EXE_OUTPUT"
        echo "Executable Size: $SIZE bytes ($(( SIZE / 1024 / 1024 )) MB)"
        echo "Executable SHA-256: $SHA"
        echo "========================================================="
    else
        echo "Error: Installer output EXE not found after compilation."
        exit 1
    fi
else
    echo "Error: Portable Wine or ISCC compiler missing."
    exit 1
fi
