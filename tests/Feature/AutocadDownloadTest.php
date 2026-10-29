<?php

namespace Tests\Feature;

use App\Http\Controllers\AutocadDownloadController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AutocadDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_autocad_download_route_serves_exe_file(): void
    {
        Storage::fake('public');

        $fakeContent = 'MZ' . str_repeat("\x90", 100);
        Storage::disk('public')->put(AutocadDownloadController::RELATIVE_STORAGE_PATH, $fakeContent);

        $response = $this->get(route('autocad.download'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.microsoft.portable-executable');
        $this->assertTrue(
            str_contains($response->headers->get('Content-Disposition'), AutocadDownloadController::RELEASE_FILENAME)
        );
    }

    public function test_maktabat_download_alias_serves_exe_file(): void
    {
        Storage::fake('public');

        $fakeContent = 'MZ' . str_repeat("\x90", 100);
        Storage::disk('public')->put(AutocadDownloadController::RELATIVE_STORAGE_PATH, $fakeContent);

        $response = $this->get(route('maktabat.download'));

        $response->assertStatus(200);
        $this->assertTrue(
            str_contains($response->headers->get('Content-Disposition'), AutocadDownloadController::RELEASE_FILENAME)
        );
    }

    public function test_api_autocad_download_route_serves_exe_file(): void
    {
        Storage::fake('public');

        $fakeContent = 'MZ' . str_repeat("\x90", 100);
        Storage::disk('public')->put(AutocadDownloadController::RELATIVE_STORAGE_PATH, $fakeContent);

        $response = $this->get(route('api.v1.autocad.download'));

        $response->assertStatus(200);
        $this->assertTrue(
            str_contains($response->headers->get('Content-Disposition'), AutocadDownloadController::RELEASE_FILENAME)
        );
    }

    public function test_autocad_download_does_not_require_authentication(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put(AutocadDownloadController::RELATIVE_STORAGE_PATH, 'PE_DUMMY_CONTENT');

        $response = $this->get('/autocad/download');
        $response->assertStatus(200);
    }

    public function test_autocad_download_handles_missing_file_gracefully(): void
    {
        Storage::fake('public');

        $response = $this->get(route('autocad.download'));
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_autocad_download_resolves_from_candidate_subpaths(): void
    {
        Storage::fake('public');

        // Test with Maktabat/ prefixed candidate
        $fakeContent = 'MZ' . str_repeat("\x90", 50);
        Storage::disk('public')->put('maktabat/' . AutocadDownloadController::RELEASE_FILENAME, $fakeContent);

        $response = $this->get(route('autocad.download'));
        $response->assertStatus(200);
        $this->assertTrue(
            str_contains($response->headers->get('Content-Disposition'), AutocadDownloadController::RELEASE_FILENAME)
        );
    }

    public function test_autocad_download_missing_file_redirects_with_tab_and_tool_session(): void
    {
        Storage::fake('public');

        $response = $this->get(route('autocad.download'));
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $response->assertSessionHas('active_tab', 4);
        $response->assertSessionHas('selected_tool', 'autocad');
    }

    public function test_homepage_renders_autocad_tool_section_and_download_button(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('مكتبة أدوات الأوتوكاد');
        $response->assertSee(route('autocad.download'));
        $response->assertSee('v' . AutocadDownloadController::CURRENT_VERSION);
    }

    public function test_homepage_displays_error_alert_when_session_has_error(): void
    {
        $response = $this->withSession(['error' => 'AutoCAD Tool installer is currently unavailable for download.'])->get('/');

        $response->assertStatus(200);
        $response->assertSee('AutoCAD Tool installer is currently unavailable for download.');
    }
}

