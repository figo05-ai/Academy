<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\SketchupLicense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminSketchupLicenseTest extends TestCase
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

    /**
     * Test 1: Admin can activate SketchUp license for a user.
     */
    public function test_admin_can_activate_sketchup_license(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson("/admin/users/{$this->student->id}/sketchup-license", [
                'is_active' => 1,
                'notes' => 'Activated by Admin',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => 'تم تحديث ترخيص Sketchup بنجاح!']);

        $this->assertDatabaseHas('sketchup_licenses', [
            'user_id' => $this->student->id,
            'is_active' => true,
            'notes' => 'Activated by Admin',
        ]);
    }

    /**
     * Test 2: Admin can deactivate SketchUp license for a user.
     */
    public function test_admin_can_deactivate_sketchup_license(): void
    {
        SketchupLicense::create([
            'user_id' => $this->student->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/admin/users/{$this->student->id}/sketchup-license", [
                'is_active' => 0,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('sketchup_licenses', [
            'user_id' => $this->student->id,
            'is_active' => false,
        ]);
    }

    /**
     * Test 3: Admin can set an expiration date for a license.
     */
    public function test_admin_can_set_expiration_date(): void
    {
        $futureDate = Carbon::now()->addDays(60)->format('Y-m-d\TH:i');

        $response = $this->actingAs($this->admin)
            ->putJson("/admin/users/{$this->student->id}/sketchup-license", [
                'is_active' => 1,
                'expires_at' => $futureDate,
            ]);

        $response->assertStatus(200);

        $license = $this->student->fresh()->sketchupLicense;
        $this->assertNotNull($license->expires_at);
        $this->assertTrue($license->expires_at->isFuture());
    }

    /**
     * Test 4: Admin can remove expiration date (making it lifetime access).
     */
    public function test_admin_can_remove_expiration_date(): void
    {
        SketchupLicense::create([
            'user_id' => $this->student->id,
            'is_active' => true,
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/admin/users/{$this->student->id}/sketchup-license", [
                'is_active' => 1,
                'expires_at' => null,
            ]);

        $response->assertStatus(200);

        $license = $this->student->fresh()->sketchupLicense;
        $this->assertNull($license->expires_at);
    }

    /**
     * Test 5: Unauthorized non-admin users cannot change licenses.
     */
    public function test_unauthorized_users_cannot_change_licenses(): void
    {
        $otherStudent = User::factory()->create([
            'is_admin' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->student)
            ->putJson("/admin/users/{$otherStudent->id}/sketchup-license", [
                'is_active' => 1,
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test 6: Unauthenticated requests to Admin license routes are redirected to login.
     */
    public function test_unauthenticated_requests_to_admin_license_routes_are_blocked(): void
    {
        $response = $this->putJson("/admin/users/{$this->student->id}/sketchup-license", [
            'is_active' => 1,
        ]);

        $response->assertStatus(302);
    }

    /**
     * Test 7: License creation via Admin requires explicit validation.
     */
    public function test_admin_license_update_validates_payload(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson("/admin/users/{$this->student->id}/sketchup-license", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }

    /**
     * Test 8: Admin can retrieve SketchUp license details via GET JSON.
     */
    public function test_admin_can_get_user_sketchup_license_details(): void
    {
        SketchupLicense::create([
            'user_id' => $this->student->id,
            'is_active' => true,
            'notes' => 'Test admin note',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/admin/users/{$this->student->id}/sketchup-license");

        $response->assertStatus(200)
            ->assertJson([
                'user' => ['id' => $this->student->id, 'email' => $this->student->email],
                'license' => [
                    'is_active' => true,
                    'notes' => 'Test admin note',
                ],
            ]);
    }

    /**
     * Test 9: Admin users table page renders SketchUp license button, status cell, and modal.
     */
    public function test_admin_users_page_renders_sketchup_elements(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/users');

        $response->assertStatus(200)
            ->assertSee('btn-sketchup-license')
            ->assertSee('sketchup-license-modal')
            ->assertSee('sketchup-status-cell')
            ->assertSee('ترخيص SketchUp');
    }
}
