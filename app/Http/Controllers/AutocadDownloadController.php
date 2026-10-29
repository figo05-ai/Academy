<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AutocadDownloadController extends Controller
{
    /**
     * Constant specifying the current published release version and filename.
     */
    public const CURRENT_VERSION = '3.2.0';
    public const RELEASE_FILENAME = 'Maktabat_Office_Setup_3.2.0_Licensed.exe';
    public const RELATIVE_STORAGE_PATH = 'Maktabat/Maktabat_Office_Setup_3.2.0_Licensed.exe';

    /**
     * Download the published AutoCAD Tool / Maktabat Office Windows EXE Installer.
     *
     * @return BinaryFileResponse|StreamedResponse|\Illuminate\Http\RedirectResponse
     */
    public function download()
    {
        $resolvedPath = $this->resolveInstallerPath();

        if ($resolvedPath && file_exists($resolvedPath)) {
            $this->ensurePublicDiskCopy($resolvedPath);

            return response()->download($resolvedPath, self::RELEASE_FILENAME, [
                'Content-Type' => 'application/vnd.microsoft.portable-executable',
                'Cache-Control' => 'no-cache, private',
            ]);
        }

        // Check if Storage disk has it directly (e.g. Storage::fake in tests)
        $disk = Storage::disk('public');
        if ($disk->exists(self::RELATIVE_STORAGE_PATH)) {
            return $disk->download(self::RELATIVE_STORAGE_PATH, self::RELEASE_FILENAME, [
                'Content-Type' => 'application/vnd.microsoft.portable-executable',
                'Cache-Control' => 'no-cache, private',
            ]);
        }

        return redirect()->back()
            ->withInput()
            ->with('error', 'AutoCAD Tool installer is currently unavailable for download.')
            ->with('active_tab', 4)
            ->with('selected_tool', 'autocad');
    }

    /**
     * Locate the installer file across candidate directories on disk.
     *
     * @return string|null
     */
    public function resolveInstallerPath(): ?string
    {
        $disk = Storage::disk('public');

        // 1. Check relative storage path on public disk
        try {
            if ($disk->exists(self::RELATIVE_STORAGE_PATH)) {
                return $disk->path(self::RELATIVE_STORAGE_PATH);
            }
        } catch (\Throwable $e) {
            // Ignore if path() is not supported on this adapter
        }

        // Candidate relative subpaths to check on disk
        $relativeCandidates = [
            'Maktabat/' . self::RELEASE_FILENAME,
            'maktabat/' . self::RELEASE_FILENAME,
            'autocad/releases/' . self::RELEASE_FILENAME,
            'maktabat/releases/' . self::RELEASE_FILENAME,
            'autocad/' . self::RELEASE_FILENAME,
            self::RELEASE_FILENAME,
            'Maktabat/Maktabat_Office_Setup_2020-2027.exe',
            'maktabat/Maktabat_Office_Setup_2020-2027.exe',
            'autocad/Maktabat_Office_Setup_2020-2027.exe',
            'Maktabat_Office_Setup_2020-2027.exe',
        ];

        foreach ($relativeCandidates as $rel) {
            try {
                if ($disk->exists($rel)) {
                    return $disk->path($rel);
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        // When running in testing environment, avoid falling back to host filesystem
        if (app()->environment('testing')) {
            return null;
        }

        // 2. Check base directories on filesystem (covers host mounts, docker-compose volume mounts, dev/prod variations)
        $rootCandidates = array_unique(array_filter([
            storage_path('app/public'),
            base_path('storage-public'),
            base_path('storage/app/public'),
            base_path('storage/app'),
            public_path('storage'),
            '/var/www/storage-public',
            '/var/www/storage/app/public',
        ]));

        $subFolders = ['Maktabat', 'maktabat', 'autocad/releases', 'autocad', ''];
        $fileNames = [
            self::RELEASE_FILENAME,
            'Maktabat_Office_Setup_2020-2027.exe',
            'Maktabat_Office_Setup_3.2.0.exe',
        ];

        foreach ($rootCandidates as $rootDir) {
            if (! is_dir($rootDir)) {
                continue;
            }

            foreach ($subFolders as $subFolder) {
                $dir = $subFolder !== '' ? rtrim($rootDir, '/') . '/' . $subFolder : $rootDir;
                if (! is_dir($dir)) {
                    continue;
                }

                foreach ($fileNames as $fileName) {
                    $fullPath = $dir . '/' . $fileName;
                    if (is_file($fullPath) && file_exists($fullPath)) {
                        return $fullPath;
                    }
                }

                // Glob for any *.exe file in Maktabat / maktabat / autocad directory as a fallback
                $exeMatches = glob($dir . '/*.exe');
                if (! empty($exeMatches)) {
                    foreach ($exeMatches as $exePath) {
                        if (is_file($exePath) && file_exists($exePath)) {
                            return $exePath;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Ensure the resolved file is mirrored to the standard public storage disk path.
     */
    protected function ensurePublicDiskCopy(string $sourcePath): void
    {
        try {
            $disk = Storage::disk('public');
            $targetPath = self::RELATIVE_STORAGE_PATH;

            if (! $disk->exists($targetPath)) {
                $targetDir = dirname($disk->path($targetPath));
                if (! is_dir($targetDir)) {
                    @mkdir($targetDir, 0775, true);
                }
                if ($sourcePath !== $disk->path($targetPath)) {
                    @copy($sourcePath, $disk->path($targetPath));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to copy AutoCAD installer to public storage: ' . $e->getMessage());
        }
    }
}

