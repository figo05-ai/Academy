<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class SketchupDownloadController extends Controller
{
    public const CURRENT_VERSION = '0.6.2';
    public const RELEASE_FILENAME = 'Technical_Office_Tools_v0.6.2.rbz';
    public const RELATIVE_STORAGE_PATH = 'sketchup/releases/Technical_Office_Tools_v0.6.2.rbz';

    public function download()
    {
        $disk = Storage::disk('public');
        $storagePath = self::RELATIVE_STORAGE_PATH;
        if (! $disk->exists($storagePath)) {
            if ($disk->exists('sketchup/' . self::RELEASE_FILENAME)) {
                $storagePath = 'sketchup/' . self::RELEASE_FILENAME;
            } elseif ($disk->exists(self::RELEASE_FILENAME)) {
                $storagePath = self::RELEASE_FILENAME;
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
