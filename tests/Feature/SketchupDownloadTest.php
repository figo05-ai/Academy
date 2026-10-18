<?php
namespace Tests\Feature;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\SketchupDownloadController as Release;
use Tests\TestCase;
class SketchupDownloadTest extends TestCase {
    public function test_unapproved_package_is_not_downloadable(): void {
        Storage::fake('public');
        Storage::disk('public')->put(Release::RELATIVE_STORAGE_PATH,'unapproved fixture');
        config(['sketchup.release_sha256'=>null]);
        $this->get('/sketchup/download')->assertStatus(503);
    }
    public function test_changed_package_is_rejected(): void {
        Storage::fake('public');
        Storage::disk('public')->put(Release::RELATIVE_STORAGE_PATH,'modified fixture');
        config(['sketchup.release_sha256'=>hash('sha256','approved fixture')]);
        $this->get('/sketchup/download')->assertStatus(503);
    }
    public function test_exact_approved_bytes_are_downloaded(): void {
        // Tests download integrity only; does not claim this fixture is encrypted/signed.
        Storage::fake('public');
        Storage::disk('public')->put(Release::RELATIVE_STORAGE_PATH,'approved fixture');
        config(['sketchup.release_sha256'=>hash('sha256','approved fixture')]);
        $this->get('/sketchup/download')->assertDownload(Release::RELEASE_FILENAME);
    }
}
