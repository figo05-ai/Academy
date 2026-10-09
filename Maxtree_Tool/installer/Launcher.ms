/*
 * Launcher.ms — Maxtree Tool Desktop Launcher for Autodesk 3ds Max 2025-2027
 * Academy + Maxtree Tool Integration
 */
(
    local targetScript = ""

    -- 1. Check User APPDATA installation directory
    local appDataEnv = systemTools.getEnvVariable "APPDATA"
    if appDataEnv != undefined and appDataEnv != "" do
    (
        local candidate = appDataEnv + "\\Autodesk\\ApplicationPlugins\\Maxtree.bundle\\Contents\\library_bootstrap.py"
        if (doesFileExist candidate) do targetScript = candidate
    )

    -- 2. Check ALLUSERSPROFILE / ProgramData installation directory
    if targetScript == "" do
    (
        local progDataEnv = systemTools.getEnvVariable "ALLUSERSPROFILE"
        if progDataEnv != undefined and progDataEnv != "" do
        (
            local candidate = progDataEnv + "\\Autodesk\\ApplicationPlugins\\Maxtree.bundle\\Contents\\library_bootstrap.py"
            if (doesFileExist candidate) do targetScript = candidate
        )
    )

    -- 3. Execute Maxtree Tool Python Bootstrap if found
    if targetScript != "" then
    (
        python.ExecuteFile targetScript
    )
    else
    (
        messageBox "Could not find Maxtree.bundle installation.\n\nPlease verify that Maxtree Tool has been installed using Maxtree_Setup.exe." title:"Maxtree Tool Launcher Error"
    )
)
