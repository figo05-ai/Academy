<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\MaxtreeLicense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminMaxtreeLicenseTest extends TestCase
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
     * Test 1: Admin can activate Maxtree license for a user.
     */
    public function test_admin_can_activate_maxtree_license(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson("/admin/users/{$this->student->id}/maxtree-license", [
                'is_active' => 1,
                'notes' => 'Activated by Admin',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => 'تم تحديث ترخيص Maxtree بنجاح!']);

        $this->assertDatabaseHas('maxtree_licenses', [
            'user_id' => $this->student->id,
            'is_active' => true,
            'notes' => 'Activated by Admin',
        ]);
    }

    /**
     * Test 2: Admin can deactivate Maxtree license for a user.
     */
    public function test_admin_can_deactivate_maxtree_license(): void
    {
        MaxtreeLicense::create([
            'user_id' => $this->student->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/admin/users/{$this->student->id}/maxtree-license", [
                'is_active' => 0,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('maxtree_licenses', [
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
            ->putJson("/admin/users/{$this->student->id}/maxtree-license", [
                'is_active' => 1,
                'expires_at' => $futureDate,
            ]);

        $response->assertStatus(200);

        $license = $this->student->fresh()->maxtreeLicense;
        $this->assertNotNull($license->expires_at);
        $this->assertTrue($license->expires_at->isFuture());
    }

    /**
     * Test 4: Admin can remove expiration date (making it lifetime access).
     */
    public function test_admin_can_remove_expiration_date(): void
    {
        MaxtreeLicense::create([
            'user_id' => $this->student->id,
            'is_active' => true,
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/admin/users/{$this->student->id}/maxtree-license", [
                'is_active' => 1,
                'expires_at' => null,
            ]);

        $response->assertStatus(200);

        $license = $this->student->fresh()->maxtreeLicense;
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
            ->putJson("/admin/users/{$otherStudent->id}/maxtree-license", [
                'is_active' => 1,
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test 6: Unauthenticated requests to Admin license routes are redirected to login.
     */
    public function test_unauthenticated_requests_to_admin_license_routes_are_blocked(): void
    {
        $response = $this->putJson("/admin/users/{$this->student->id}/maxtree-license", [
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
            ->putJson("/admin/users/{$this->student->id}/maxtree-license", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }

    /**
     * Test 8: Course activation status remains unchanged when modifying Maxtree license.
     */
    public function test_course_activation_remains_unchanged_when_modifying_maxtree_license(): void
    {
        $course = Course::create([
            'title' => 'Architectural Render Course',
            'description' => 'Test course',
            'price' => 120,
        ]);

        $this->student->courses()->attach($course->id, ['status' => 'active']);

        // Admin updates Maxtree License
        $this->actingAs($this->admin)
            ->putJson("/admin/users/{$this->student->id}/maxtree-license", [
                'is_active' => 1,
            ]);

        $this->student->refresh();

        // Course enrollment status remains 'active'
        $this->assertEquals('active', $this->student->courses()->first()->pivot->status);
    }

    /**
     * Test 9: Active API token loses authorization after Admin deactivates license.
     */
    public function test_active_api_token_loses_authorization_after_admin_deactivates_license(): void
    {
        MaxtreeLicense::create([
            'user_id' => $this->student->id,
            'is_active' => true,
        ]);

        $token = $this->student->createToken('test-token', ['maxtree:access'])->plainTextToken;

        // API status check initially succeeds
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/maxtree/license/status')
            ->assertStatus(200);

        // Admin deactivates license via Admin endpoint
        $this->actingAs($this->admin)
            ->putJson("/admin/users/{$this->student->id}/maxtree-license", [
                'is_active' => 0,
            ])
            ->assertStatus(200);

        // Subsequent API status check is immediately denied with 403
        auth()->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/maxtree/license/status')
            ->assertStatus(403)
            ->assertJson(['error_code' => 'LICENSE_INACTIVE']);
    }

    /**
     * Test 10: Expired license denies API authorization immediately after Admin sets past expiration.
     */
    public function test_expired_license_denies_api_authorization(): void
    {
        MaxtreeLicense::create([
            'user_id' => $this->student->id,
            'is_active' => true,
        ]);

        $token = $this->student->createToken('test-token', ['maxtree:access'])->plainTextToken;

        // Admin sets expiration date in the past
        $pastDate = Carbon::now()->subMinute()->format('Y-m-d\TH:i');
        $this->actingAs($this->admin)
            ->putJson("/admin/users/{$this->student->id}/maxtree-license", [
                'is_active' => 1,
                'expires_at' => $pastDate,
            ]);

        // API status check is immediately denied
        auth()->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/maxtree/license/status')
            ->assertStatus(403)
            ->assertJson(['error_code' => 'LICENSE_EXPIRED']);
    }

    /**
     * Test 11: Admin changes to license are reflected by GET /admin/users/{user}/maxtree-license.
     */
    public function test_admin_can_get_user_maxtree_license_details(): void
    {
        MaxtreeLicense::create([
            'user_id' => $this->student->id,
            'is_active' => true,
            'notes' => 'Test admin note',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/admin/users/{$this->student->id}/maxtree-license");

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
     * Test 12: Security Hardening Test — Web session cookie cannot access API token protected routes.
     */
    public function test_web_session_cannot_access_maxtree_token_protected_api(): void
    {
        MaxtreeLicense::create([
            'user_id' => $this->student->id,
            'is_active' => true,
        ]);

        // Login student via Web Session (without Bearer token)
        $response = $this->actingAs($this->student)
            ->getJson('/api/v1/maxtree/license/status');

        // Web session cookie without PersonalAccessToken Bearer header must be rejected with 401
        $response->assertStatus(401)
            ->assertJson(['error_code' => 'UNAUTHORIZED']);
    }
}
