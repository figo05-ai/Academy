# MAXTREE TOOL v3.0.0 — WINDOWS 3DS MAX TESTER GUIDE

This guide explains how to install, launch, and test the **Maxtree Tool** plugin on a Windows machine running **Autodesk 3ds Max 2025–2027**.

---

## 1. INSTALLATION

1. Transfer `Maxtree_Setup_3.0.0_x64.exe` to your Windows PC.
2. Double-click `Maxtree_Setup_3.0.0_x64.exe` to run the installer.
3. Complete the setup wizard (no administrator rights required; installs into `%ALLUSERSPROFILE%\Autodesk\ApplicationPlugins\Maxtree.bundle`).
4. Upon completion, verify that a shortcut script named `Launcher.ms` has been placed on your **Windows Desktop**.

---

## 2. LAUNCHING INSIDE 3DS MAX

1. Open **Autodesk 3ds Max 2025, 2026, or 2027**.
2. Locate `Launcher.ms` on your Windows Desktop.
3. **Drag and drop** `Launcher.ms` into the active 3ds Max viewport window, OR go to **Scripting -> Run Script...** in 3ds Max and choose `Launcher.ms`.
4. The **Maxtree Tool** window will open.

---

## 3. TESTING VERIFICATION STEPS

### Test 1: Locked State Verification
- Upon opening, the tool interface should display `🔒 [LOCKED] Login required to access Maxtree Tool.`
- All model insertion, asset creation, and collection editing buttons should be disabled.
- Only the **Login** button in the top header should be active.

### Test 2: Authentication
- Click the green **Login** button.
- Enter your registered **Academy Account** email address and password.
- Click **Login**.
- Upon server verification, the status badge will update to **ACTIVE**, and the plugin interface will unlock automatically.

### Test 3: Browsing & Model Insertion
- Select a collection or category from the dropdown filters.
- Choose a model block to preview its details and thumbnail.
- Select insertion mode (**Copy** or **Instance**).
- Click **Insert Block** to merge the model into your active 3ds Max scene.
- Verify material textures and Corona / V-Ray material assignments.

### Test 4: Logout
- Click the **Logout** button in the header widget.
- Verify that session tokens are revoked and the interface locks immediately.

---

## 4. REPORTING ISSUES & LOG COLLECTION

If any error occurs:
- Take a screenshot of the error message or dialog.
- Locate persistent settings and logs in `%APPDATA%\Maxtree_Tool\settings\`.
- Send feedback to the developer team with sanitized log excerpts.
