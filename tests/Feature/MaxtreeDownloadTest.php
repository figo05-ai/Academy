<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Http\Controllers\MaxtreeDownloadController;

class MaxtreeDownloadTest extends TestCase
{
    public function test_maxtree_download_route_serves_exe_file()
    {
        Storage::fake('public');
        
        $fakeContent = 'MZ' . str_repeat("\x90", 100);
        Storage::disk('public')->put('maxtree/releases/Maxtree_Setup_3.0.0_x64.exe', $fakeContent);

        $response = $this->get(route('maxtree.download'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.microsoft.portable-executable');
        $this->assertTrue(
            str_contains($response->headers->get('Content-Disposition'), 'Maxtree_Setup_3.0.0_x64.exe')
        );
    }

    public function test_maxtree_download_does_not_require_authentication()
    {
        Storage::fake('public');
        Storage::disk('public')->put('maxtree/releases/Maxtree_Setup_3.0.0_x64.exe', 'PE_DUMMY_CONTENT');

        $response = $this->get('/maxtree/download');
        $response->assertStatus(200);
    }

    public function test_maxtree_download_handles_missing_file_gracefully()
    {
        Storage::fake('public');

        $response = $this->get(route('maxtree.download'));
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
