# PowerShell Build Script for Maxtree Tool 3ds Max Plugin Bundle & Installer
# Usage: powershell -ExecutionPolicy Bypass -File build_installer.ps1

$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
$RootDir = Resolve-Path "$ScriptDir\.."
$SourceDir = Resolve-Path "$RootDir\Maxtree_Tool"
$DistDir = "$RootDir\dist"
$StagingBundle = "$DistDir\staging\Maxtree.bundle"
$ContentsDir = "$StagingBundle\Contents"

Write-Host "=========================================================" -ForegroundColor Cipher
Write-Host "Building Maxtree Tool Autodesk ApplicationPlugins Bundle" -ForegroundColor Cyan
Write-Host "=========================================================" -ForegroundColor Cipher

# 1. Clean Staging Directory
if (Test-Path $DistDir) {
    Remove-Item -Recurse -Force $DistDir
}
New-Item -ItemType Directory -Path $ContentsDir -Force | Out-Null

# 2. Whitelisted Files
$WhitelistedFiles = @(
    "Launch.ms",
    "library_bootstrap.py",
    "maxtree_api_client.py",
    "maxtree_license_manager.py",
    "auth_ui.py",
    "maxtree_library.py",
    "block_actions.py",
    "block_catalog.py",
    "block_runtime.py",
    "collection_store.py",
    "collection_ui.py",
    "collection_scan.py",
    "asset_store.py",
    "asset_ui.py",
    "asset_materials.py",
    "academy_logo.png",
    "catalog.json"
)

# 3. Copy Application Files
foreach ($file in $WhitelistedFiles) {
    $src = Join-Path $SourceDir $file
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination $ContentsDir -Force
        Write-Host "Copied: $file" -ForegroundColor Green
    } else {
        Write-Error "Required distribution file missing: $file"
    }
}

# Copy Package Manifest & MacroScript
Copy-Item -Path "$ScriptDir\PackageContents.xml" -Destination $StagingBundle -Force
Copy-Item -Path "$ScriptDir\MaxtreeMacro.ms" -Destination $ContentsDir -Force

# 4. Generate Release Manifest (JSON with SHA-256)
$ManifestData = @{
    version = "3.0.0"
    build_date = (Get-Date).ToString("yyyy-MM-ddTHH:mm:ssZ")
    series_min = "2025"
    series_max = "2027"
    files = @()
}

$FilesToHash = Get-ChildItem -Recurse -File $StagingBundle
foreach ($f in $FilesToHash) {
    $relPath = $f.FullName.Substring($StagingBundle.Length + 1).Replace("\", "/")
    $hash = (Get-FileHash -Path $f.FullName -Algorithm SHA256).Hash.ToLower()
    $ManifestData.files += @{
        path = $relPath
        size_bytes = $f.Length
        sha256 = $hash
    }
}

$ManifestJson = $ManifestData | ConvertTo-Json -Depth 4
$ManifestJson | Out-File -FilePath "$DistDir\release_manifest.json" -Encoding utf8
Write-Host "Generated release_manifest.json with $($ManifestData.files.Count) files." -ForegroundColor Cyan

# 5. Compile Installer if Inno Setup (ISCC.exe) is available
$Iscc = Get-Command "iscc.exe" -ErrorAction SilentlyContinue
if ($Iscc) {
    Write-Host "Compiling Inno Setup Installer with ISCC.exe..." -ForegroundColor Yellow
    & $Iscc.Source "$ScriptDir\Maxtree_Setup.iss" /O"$DistDir"
    $ExeFile = Get-ChildItem -Path $DistDir -Filter "Maxtree_Setup_*.exe" | Select-Object -First 1
    if ($ExeFile) {
        $ExeHash = (Get-FileHash -Path $ExeFile.FullName -Algorithm SHA256).Hash.ToLower()
        Write-Host "Installer built successfully: $($ExeFile.FullName)" -ForegroundColor Green
        Write-Host "Installer SHA-256: $ExeHash" -ForegroundColor Green
    }
} else {
    Write-Host "Notice: ISCC.exe not found in PATH. Distribution bundle staged in dist/staging/Maxtree.bundle." -ForegroundColor Yellow
}
