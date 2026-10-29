<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class SketchupDownloadController extends Controller
{
    public const CURRENT_VERSION = '0.6.7';
    public const RELEASE_FILENAME = 'Technical_Office_Tools_v0.6.7.rbz.zip';
    public const RELATIVE_STORAGE_PATH = 'sketchup/releases/Technical_Office_Tools_v0.6.7.rbz.zip';

    public function download()
    {
        $disk = Storage::disk('public');
        $storagePath = self::RELATIVE_STORAGE_PATH;
        if (! $disk->exists($storagePath)) {
            $altFilename = str_ends_with(self::RELEASE_FILENAME, '.zip')
                ? substr(self::RELEASE_FILENAME, 0, -4)
                : self::RELEASE_FILENAME . '.zip';

            $candidates = [
                'sketchup/releases/' . $altFilename,
                'sketchup/' . self::RELEASE_FILENAME,
                'sketchup/' . $altFilename,
                self::RELEASE_FILENAME,
                $altFilename,
            ];

            foreach ($candidates as $candidate) {
                if ($disk->exists($candidate)) {
                    $storagePath = $candidate;
                    break;
                }
            }
        }

        if (! $disk->exists($storagePath) && ! app()->environment('testing')) {
            $rootCandidates = array_unique(array_filter([
                storage_path('app/public'),
                base_path('storage-public'),
                base_path('storage/app/public'),
                public_path('storage'),
                '/var/www/storage-public',
                '/var/www/storage/app/public',
            ]));

            foreach ($rootCandidates as $rootDir) {
                $candidatePath = rtrim($rootDir, '/') . '/sketchup/releases/' . self::RELEASE_FILENAME;
                if (file_exists($candidatePath)) {
                    $targetDir = dirname($disk->path(self::RELATIVE_STORAGE_PATH));
                    if (! is_dir($targetDir)) {
                        @mkdir($targetDir, 0775, true);
                    }
                    @copy($candidatePath, $disk->path(self::RELATIVE_STORAGE_PATH));
                    $storagePath = self::RELATIVE_STORAGE_PATH;
                    break;
                }
            }
        }

        abort_unless($disk->exists($storagePath), 404, 'ملف الإضافة غير متاح حاليًا.');

        $expected = config('sketchup.release_sha256');
        abort_unless(is_string($expected) && preg_match('/\A[a-f0-9]{64}\z/i', $expected), 503, 'النسخة المحمية قيد التجهيز.');
        $stream = $disk->readStream($storagePath);
        abort_unless(is_resource($stream), 503);
        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);
            abort_unless(hash_equals(strtolower($expected), hash_final($hash)), 503, 'ملف الإصدار غير معتمد.');
        } finally {
            fclose($stream);
        }

        return $disk->download($storagePath, self::RELEASE_FILENAME, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
