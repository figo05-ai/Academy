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
    public function test_api_download_route_downloads_package(): void {
        Storage::fake('public');
        Storage::disk('public')->put(Release::RELATIVE_STORAGE_PATH,'approved fixture');
        config(['sketchup.release_sha256'=>hash('sha256','approved fixture')]);
        $this->get('/api/v1/sketchup/download')->assertDownload(Release::RELEASE_FILENAME);
        $this->get('/api/sketchup/download')->assertDownload(Release::RELEASE_FILENAME);
    }
    public function test_fallback_storage_path_downloads_package(): void {
        Storage::fake('public');
        Storage::disk('public')->put('sketchup/'.Release::RELEASE_FILENAME,'approved fixture');
        config(['sketchup.release_sha256'=>hash('sha256','approved fixture')]);
        $this->get('/sketchup/download')->assertDownload(Release::RELEASE_FILENAME);
    }
    public function test_real_packaged_release_matches_configured_hash(): void {
        if (! Storage::disk('public')->exists(Release::RELATIVE_STORAGE_PATH)) {
            $this->markTestSkipped('Real release file not present.');
        }
        $response = $this->get('/sketchup/download');
        $response->assertOk();
        $response->assertDownload(Release::RELEASE_FILENAME);
    }
}
