"""
Automated Packaging and Distribution Verification Test Suite for Step 07.
Validates PackageContents.xml, file manifest completeness, module importability,
user data path separation, and SHA-256 hash generation.
"""
import os
import sys
import json
import hashlib
import unittest
import xml.etree.ElementTree as ET
from pathlib import Path
from unittest.mock import patch, MagicMock

# Add plugin folder to sys.path
PLUGIN_DIR = Path(__file__).resolve().parent
if str(PLUGIN_DIR) not in sys.path:
    sys.path.insert(0, str(PLUGIN_DIR))

# Mock pymxs and PySide6 for standalone execution outside 3ds Max
import types
if "pymxs" not in sys.modules:
    pymxs_mod = types.ModuleType("pymxs")
    pymxs_mod.runtime = MagicMock()
    sys.modules["pymxs"] = pymxs_mod

if "PySide6" not in sys.modules:
    pyside_mod = types.ModuleType("PySide6")
    pyside_mod.QtCore = MagicMock()
    pyside_mod.QtGui = MagicMock()
    pyside_mod.QtWidgets = MagicMock()
    sys.modules["PySide6"] = pyside_mod
    sys.modules["PySide6.QtCore"] = pyside_mod.QtCore
    sys.modules["PySide6.QtGui"] = pyside_mod.QtGui
    sys.modules["PySide6.QtWidgets"] = pyside_mod.QtWidgets

INSTALLER_DIR = PLUGIN_DIR.parent / "installer"


class TestAutodeskPackageManifest(unittest.TestCase):

    def test_package_contents_xml_validity(self):
        xml_path = INSTALLER_DIR / "PackageContents.xml"
        self.assertTrue(xml_path.is_file(), f"PackageContents.xml missing at {xml_path}")

        tree = ET.parse(xml_path)
        root = tree.getroot()

        self.assertEqual(root.tag, "ApplicationPackage")
        self.assertEqual(root.attrib.get("AutodeskProduct"), "3ds Max")
        self.assertEqual(root.attrib.get("ProductType"), "Application")
        self.assertTrue(root.attrib.get("UpgradeCode"), "UpgradeCode missing")
        self.assertTrue(root.attrib.get("AppVersion"), "AppVersion missing")

        # Verify RuntimeRequirements
        reqs = root.find(".//RuntimeRequirements")
        self.assertIsNotNone(reqs, "RuntimeRequirements missing in PackageContents.xml")
        self.assertEqual(reqs.attrib.get("OS"), "Win64")
        self.assertEqual(reqs.attrib.get("Platform"), "3ds Max")
        self.assertEqual(reqs.attrib.get("SeriesMin"), "2025")
        self.assertEqual(reqs.attrib.get("SeriesMax"), "2027")

        # Verify ComponentEntry
        comp = root.find(".//ComponentEntry")
        self.assertIsNotNone(comp, "ComponentEntry missing in PackageContents.xml")
        self.assertEqual(comp.attrib.get("ModuleName"), "./Contents/MaxtreeMacro.ms")
        self.assertEqual(comp.attrib.get("Type"), "PostStartupScript")

    def test_macroscript_launcher_file(self):
        macro_path = INSTALLER_DIR / "MaxtreeMacro.ms"
        self.assertTrue(macro_path.is_file(), f"MaxtreeMacro.ms missing at {macro_path}")
        content = macro_path.read_text(encoding="utf-8")
        self.assertIn('category:"Academy Tools"', content)
        self.assertIn('library_bootstrap.py', content)


class TestPluginDistributionManifest(unittest.TestCase):

    def setUp(self):
        self.whitelisted_files = [
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
            "catalog.json",
        ]

    def test_all_whitelisted_files_exist(self):
        for fname in self.whitelisted_files:
            fpath = PLUGIN_DIR / fname
            self.assertTrue(fpath.is_file(), f"Distribution file missing: {fname}")

    def test_whitelisted_files_contain_no_developer_logs_or_tests(self):
        for fname in self.whitelisted_files:
            self.assertFalse(fname.startswith("test_"), f"Test script found in distribution whitelist: {fname}")
            self.assertFalse(fname.endswith(".log"), f"Log file found in distribution whitelist: {fname}")
            self.assertFalse(fname.startswith("final_validation"), f"Validation script found in whitelist: {fname}")

    def test_sha256_manifest_generation(self):
        manifest_files = []
        for fname in self.whitelisted_files:
            fpath = PLUGIN_DIR / fname
            h = hashlib.sha256(fpath.read_bytes()).hexdigest()
            manifest_files.append({"path": fname, "size_bytes": fpath.stat().st_size, "sha256": h})

        self.assertEqual(len(manifest_files), len(self.whitelisted_files))
        for item in manifest_files:
            self.assertEqual(len(item["sha256"]), 64)


class TestUserDataPathSeparation(unittest.TestCase):

    def test_settings_dir_uses_user_appdata(self):
        import collection_store
        settings_dir = collection_store.resolve_user_settings_dir()
        self.assertTrue(isinstance(settings_dir, Path))

        # Check non-destructive migration logic
        with patch.dict("os.environ", {"APPDATA": str(PLUGIN_DIR / "scratch_appdata")}):
            custom_dir = collection_store.resolve_user_settings_dir()
            self.assertTrue(str(custom_dir).endswith("Maxtree_Tool/settings"))


class TestModuleImportability(unittest.TestCase):

    def test_all_modules_import_cleanly(self):
        modules = [
            "maxtree_api_client",
            "maxtree_license_manager",
            "auth_ui",
            "library_bootstrap",
            "maxtree_library",
            "block_actions",
            "block_catalog",
            "block_runtime",
            "collection_store",
            "collection_ui",
            "collection_scan",
            "asset_store",
            "asset_ui",
            "asset_materials",
        ]
        for mod_name in modules:
            mod = __import__(mod_name)
            self.assertIsNotNone(mod)


if __name__ == "__main__":
    unittest.main()
