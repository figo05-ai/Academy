<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $disk = Storage::disk('public');
        if ($disk->exists(self::RELATIVE_STORAGE_PATH)) {
            return $disk->download(self::RELATIVE_STORAGE_PATH, self::RELEASE_FILENAME, [
                'Content-Type' => 'application/vnd.microsoft.portable-executable',
                'Cache-Control' => 'no-cache, private',
            ]);
        }

        return redirect()->back()
            ->withInput()
            ->with('error', 'Maxtree Tool installer is currently unavailable for download.')
            ->with('active_tab', 4)
            ->with('selected_tool', 'maxtree');
    }

    /**
     * Locate the installer file across candidate directories on disk.
     *
     * @return string|null
     */
    public function resolveInstallerPath(): ?string
    {
        $disk = Storage::disk('public');

        try {
            if ($disk->exists(self::RELATIVE_STORAGE_PATH)) {
                return $disk->path(self::RELATIVE_STORAGE_PATH);
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        $relativeCandidates = [
            'maxtree/releases/' . self::RELEASE_FILENAME,
            'maxtree/' . self::RELEASE_FILENAME,
            self::RELEASE_FILENAME,
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

        if (app()->environment('testing')) {
            return null;
        }

        $rootCandidates = array_unique(array_filter([
            storage_path('app/public'),
            base_path('storage-public'),
            base_path('storage/app/public'),
            public_path('storage'),
            '/var/www/storage-public',
            '/var/www/storage/app/public',
        ]));

        $subFolders = ['maxtree/releases', 'maxtree', ''];
        $fileNames = [self::RELEASE_FILENAME];

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

                $exeMatches = glob($dir . '/*Maxtree*.exe');
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
            Log::warning('Failed to copy Maxtree installer to public storage: ' . $e->getMessage());
        }
    }
}

