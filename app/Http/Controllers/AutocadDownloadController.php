<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AutocadDownloadController extends Controller
{
    /**
     * Constant specifying the current published release version and filename.
     */
    public const CURRENT_VERSION = '3.1.0';
    public const RELEASE_FILENAME = 'Maktabat_Office_Setup_2020-2027.exe';
    public const RELATIVE_STORAGE_PATH = 'Maktabat/Maktabat_Office_Setup_2020-2027.exe';

    /**
     * Download the published AutoCAD Tool / Maktabat Office Windows EXE Installer.
     *
     * @return StreamedResponse|\Illuminate\Http\RedirectResponse
     */
    public function download()
    {
        $disk = Storage::disk('public');
        $storagePath = self::RELATIVE_STORAGE_PATH;

        if (! $disk->exists($storagePath)) {
            $candidates = [
                'autocad/releases/' . self::RELEASE_FILENAME,
                'maktabat/releases/' . self::RELEASE_FILENAME,
                'maktabat/' . self::RELEASE_FILENAME,
                'autocad/' . self::RELEASE_FILENAME,
                self::RELEASE_FILENAME,
            ];

            foreach ($candidates as $candidate) {
                if ($disk->exists($candidate)) {
                    $storagePath = $candidate;
                    break;
                }
            }
        }

        if (! $disk->exists($storagePath)) {
            return redirect()->back()->with('error', 'AutoCAD Tool installer is currently unavailable for download.');
        }

        return $disk->download($storagePath, self::RELEASE_FILENAME, [
            'Content-Type' => 'application/vnd.microsoft.portable-executable',
        ]);
    }
}
