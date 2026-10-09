<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MaxtreeDownloadController extends Controller
{
    /**
     * Constant specifying the current published release version and filename.
     */
    public const CURRENT_VERSION = '3.3.1';
    public const RELEASE_FILENAME = 'Maxtree_Setup_3.3.1_x64.exe';
    public const RELATIVE_STORAGE_PATH = 'maxtree/releases/Maxtree_Setup_3.3.1_x64.exe';

    /**
     * Download the published Maxtree Tool Windows EXE Installer.
     *
     * @return BinaryFileResponse|\Illuminate\Http\RedirectResponse
     */
    public function download()
    {
        $disk = Storage::disk('public');

        if (!$disk->exists(self::RELATIVE_STORAGE_PATH)) {
            return redirect()->back()->with('error', 'Maxtree Tool installer is currently unavailable for download.');
        }

        return $disk->download(self::RELATIVE_STORAGE_PATH, self::RELEASE_FILENAME, [
            'Content-Type' => 'application/vnd.microsoft.portable-executable',
        ]);
    }
}
