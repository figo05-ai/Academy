/* Maxtree Tool MacroScript Launcher for Autodesk 3ds Max */
macroScript MaxtreeTool
category:"Academy Tools"
toolTip:"Launch Maxtree Asset Library Browser"
buttonText:"Maxtree Tool"
(
    on execute do
    (
        local bundleDir = (getFilenamePath (getSourceFileName()))
        python.ExecuteFile (bundleDir + "library_bootstrap.py")
    )
)
