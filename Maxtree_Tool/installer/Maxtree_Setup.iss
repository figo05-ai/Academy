; Inno Setup Script for Maxtree Tool 3ds Max Plugin
; Target Host: Autodesk 3ds Max 2025 - 2027 (PySide6)

#define MyAppName "Maxtree Tool"
#define MyAppVersion "3.2.0"
#define MyAppPublisher "Academy"
#define MyAppURL "https://academy.com"
#define MyAppBundleName "Maxtree.bundle"

[Setup]
AppId={{8F23A4D1-90E2-4C12-B89B-490518712345}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
DefaultDirName={userappdata}\Autodesk\ApplicationPlugins\{#MyAppBundleName}
DefaultGroupName={#MyAppName}
PrivilegesRequired=lowest
PrivilegesRequiredOverridesAllowed=commandline dialog
OutputBaseFilename=Maxtree_Setup_{#MyAppVersion}_x64
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
ArchitecturesAllowed=x64
ArchitecturesInstallIn64BitMode=x64
UninstallDisplayIcon={app}\Contents\academy_logo.png
SetupLogging=yes

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Types]
Name: "full"; Description: "Standard 3ds Max Plugin Installation"

[Components]
Name: "plugin"; Description: "Maxtree Tool Plugin Bundle for 3ds Max 2025-2027"; Types: full; Flags: fixed

[Files]
; Autodesk ApplicationPlugins Bundle Files
Source: "PackageContents.xml"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\Maxtree_Tool\*.py"; DestDir: "{app}\Contents"; Flags: ignoreversion; Excludes: "test_*.py,final_validation.py,launcher_ui_probe.py,audit_metadata.py,general_*.py"
Source: "..\Maxtree_Tool\*.ms"; DestDir: "{app}\Contents"; Flags: ignoreversion; Excludes: "final_validation.ms,test_*.ms,launcher_ui_probe.ms,general_*.ms,Launcher.ms"
Source: "..\Maxtree_Tool\*.png"; DestDir: "{app}\Contents"; Flags: ignoreversion; Excludes: "test_*.png,installed.png"
Source: "..\Maxtree_Tool\*.json"; DestDir: "{app}\Contents"; Flags: ignoreversion; Excludes: "final_validation.json,scan_*.json,test_*.json,installed.json,audit_progress.json"
Source: "..\Maxtree_Tool\settings\config.json"; DestDir: "{app}\Contents\settings"; Flags: ignoreversion
Source: "MaxtreeMacro.ms"; DestDir: "{app}\Contents"; Flags: ignoreversion
; Windows Desktop Launcher
Source: "Launcher.ms"; DestDir: "{userdesktop}"; Flags: ignoreversion

[UninstallDelete]
Type: filesandordirs; Name: "{app}\Contents\*.pyc"
Type: filesandordirs; Name: "{app}\Contents\__pycache__"

[Code]
// Detect 3ds Max installations in Registry
function Is3dsMaxInstalled(VersionStr: String): Boolean;
var
  RegKey: String;
begin
  RegKey := 'SOFTWARE\Autodesk\3dsMax\' + VersionStr + '.0';
  Result := RegKeyExists(HKLM, RegKey) or RegKeyExists(HKCU, RegKey);
end;

function InitializeSetup(): Boolean;
var
  HasMax: Boolean;
begin
  HasMax := Is3dsMaxInstalled('27') or Is3dsMaxInstalled('26') or Is3dsMaxInstalled('25');
  if not HasMax then
  begin
    Log('Notice: No registry key for 3ds Max 2025-2027 detected. Plugin will install to ApplicationPlugins directory.');
  end;
  Result := True;
end;
