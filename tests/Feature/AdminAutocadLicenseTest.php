<?php

namespace Tests\Feature;

use App\Models\AutocadLicense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminAutocadLicenseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);

        $this->student = User::factory()->create([
            'is_admin' => false,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_activate_autocad_license(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson("/admin/users/{$this->student->id}/autocad-license", [
                'is_active' => 1,
                'notes' => 'Activated by Admin',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => 'تم تحديث ترخيص AutoCAD بنجاح!']);

        $this->assertDatabaseHas('autocad_licenses', [
            'user_id' => $this->student->id,
            'is_active' => true,
            'notes' => 'Activated by Admin',
        ]);
    }

    public function test_admin_can_deactivate_autocad_license(): void
    {
        AutocadLicense::create([
            'user_id' => $this->student->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/admin/users/{$this->student->id}/autocad-license", [
                'is_active' => 0,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('autocad_licenses', [
            'user_id' => $this->student->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_set_expiration_date(): void
    {
        $futureDate = Carbon::now()->addDays(60)->format('Y-m-d\TH:i');

        $response = $this->actingAs($this->admin)
            ->putJson("/admin/users/{$this->student->id}/autocad-license", [
                'is_active' => 1,
                'expires_at' => $futureDate,
            ]);

        $response->assertStatus(200);

        $license = $this->student->fresh()->autocadLicense;
        $this->assertNotNull($license->expires_at);
        $this->assertTrue($license->expires_at->isFuture());
    }

    public function test_admin_can_remove_expiration_date(): void
    {
        AutocadLicense::create([
            'user_id' => $this->student->id,
            'is_active' => true,
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/admin/users/{$this->student->id}/autocad-license", [
                'is_active' => 1,
                'expires_at' => null,
            ]);

        $response->assertStatus(200);

        $license = $this->student->fresh()->autocadLicense;
        $this->assertNull($license->expires_at);
    }

    public function test_unauthorized_users_cannot_change_autocad_license(): void
    {
        $otherStudent = User::factory()->create([
            'is_admin' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->student)
            ->putJson("/admin/users/{$otherStudent->id}/autocad-license", [
                'is_active' => 1,
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_get_user_autocad_license_details(): void
    {
        AutocadLicense::create([
            'user_id' => $this->student->id,
            'is_active' => true,
            'notes' => 'AutoCAD note',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/admin/users/{$this->student->id}/autocad-license");

        $response->assertStatus(200)
            ->assertJson([
                'user' => ['id' => $this->student->id, 'email' => $this->student->email],
                'license' => [
                    'is_active' => true,
                    'notes' => 'AutoCAD note',
                ],
            ]);
    }

    public function test_admin_users_view_renders_with_autocad_license(): void
    {
        AutocadLicense::create([
            'user_id' => $this->student->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/users');

        $response->assertStatus(200);
        $response->assertSee('ترخيص AutoCAD');
        $response->assertSee('btn-autocad-license');
    }
}
