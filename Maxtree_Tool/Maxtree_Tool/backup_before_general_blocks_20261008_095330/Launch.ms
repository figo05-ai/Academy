/* Always load the modules beside this exact launcher and explicitly open the UI. */
python.ExecuteFile ((getFilenamePath (getSourceFileName())) + "library_bootstrap.py")
