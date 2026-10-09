<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\MaxtreeLicense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MaxtreeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test 1: Valid user with active license login succeeds.
     */
    public function test_valid_user_with_active_license_login_succeeds(): void
    {
        $user = User::factory()->create([
            'email' => 'valid@academy.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $response = $this->postJson('/api/v1/maxtree/auth/login', [
            'email' => 'valid@academy.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'token',
                'token_type',
                'expires_in_days',
                'user' => ['id', 'name', 'email'],
                'license' => ['is_active', 'expires_at'],
            ])
            ->assertJson(['status' => 'success']);

        $this->assertNotEmpty($response->json('token'));
    }

    /**
     * Test 2: Valid user with missing license is denied.
     */
    public function test_valid_user_with_missing_license_is_denied(): void
    {
        $user = User::factory()->create([
            'email' => 'nolicense@academy.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/maxtree/auth/login', [
            'email' => 'nolicense@academy.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'LICENSE_NOT_FOUND',
            ]);
    }

    /**
     * Test 3: Valid user with inactive license is denied.
     */
    public function test_valid_user_with_inactive_license_is_denied(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@academy.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/maxtree/auth/login', [
            'email' => 'inactive@academy.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'LICENSE_INACTIVE',
            ]);
    }

    /**
     * Test 4: Valid user with expired license is denied.
     */
    public function test_valid_user_with_expired_license_is_denied(): void
    {
        $user = User::factory()->create([
            'email' => 'expired@academy.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $response = $this->postJson('/api/v1/maxtree/auth/login', [
            'email' => 'expired@academy.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'LICENSE_EXPIRED',
            ]);
    }

    /**
     * Test 5: Invalid password is denied.
     */
    public function test_invalid_password_is_denied(): void
    {
        $user = User::factory()->create([
            'email' => 'user@academy.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/maxtree/auth/login', [
            'email' => 'user@academy.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'INVALID_CREDENTIALS',
            ]);
    }

    /**
     * Test 6: Unknown email is denied safely.
     */
    public function test_unknown_email_is_denied_safely(): void
    {
        $response = $this->postJson('/api/v1/maxtree/auth/login', [
            'email' => 'unknown@academy.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'INVALID_CREDENTIALS',
            ]);
    }

    /**
     * Test 7: Globally disabled account is denied.
     */
    public function test_globally_disabled_account_is_denied(): void
    {
        $user = User::factory()->create([
            'email' => 'disabled@academy.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => false,
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/maxtree/auth/login', [
            'email' => 'disabled@academy.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'ACCOUNT_DISABLED',
            ]);
    }

    /**
     * Test 8: Active courses without Maxtree license is denied.
     */
    public function test_active_courses_without_maxtree_license_is_denied(): void
    {
        $user = User::factory()->create([
            'email' => 'student@academy.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        $course = Course::create([
            'title' => 'Laravel Architecture',
            'description' => 'Course description',
            'price' => 150,
        ]);

        $user->courses()->attach($course->id, ['status' => 'active']);

        $response = $this->postJson('/api/v1/maxtree/auth/login', [
            'email' => 'student@academy.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'LICENSE_NOT_FOUND',
            ]);
    }

    /**
     * Test 9: Active Maxtree license without courses is allowed.
     */
    public function test_active_maxtree_license_without_courses_is_allowed(): void
    {
        $user = User::factory()->create([
            'email' => 'nocourses@academy.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/maxtree/auth/login', [
            'email' => 'nocourses@academy.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);
    }

    /**
     * Test 10: Missing Bearer token returns 401 Unauthorized.
     */
    public function test_missing_bearer_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/maxtree/license/status');

        $response->assertStatus(401);
    }

    /**
     * Test 11: Invalid Bearer token returns 401 Unauthorized.
     */
    public function test_invalid_bearer_token_returns_401(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid_token_xyz')
            ->getJson('/api/v1/maxtree/license/status');

        $response->assertStatus(401);
    }

    /**
     * Test 12: Expired Sanctum token returns 401 Unauthorized.
     */
    public function test_expired_sanctum_token_returns_401(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        // Issue token that expired 1 hour ago
        $token = $user->createToken('test-token', ['maxtree:access'], Carbon::now()->subHour())->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/maxtree/license/status');

        $response->assertStatus(401);
    }

    /**
     * Test 13: Active token and active license is authorized.
     */
    public function test_active_token_and_active_license_is_authorized(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $token = $user->createToken('test-token', ['maxtree:access'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/maxtree/license/status');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'authorized' => true,
                'user' => ['id' => $user->id],
            ]);
    }

    /**
     * Test 14: Admin deactivates license -> next status check denies access.
     */
    public function test_admin_deactivates_license_denies_next_status_check(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $license = MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $token = $user->createToken('test-token', ['maxtree:access'])->plainTextToken;

        // First status check succeeds
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/maxtree/license/status')
            ->assertStatus(200);

        // Admin deactivates license
        $license->update(['is_active' => false]);

        // Next status check denies access
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/maxtree/license/status')
            ->assertStatus(403)
            ->assertJson(['error_code' => 'LICENSE_INACTIVE']);
    }

    /**
     * Test 15: Admin changes license expiration -> next status check reflects change.
     */
    public function test_admin_changes_license_expiration_reflects_on_next_status_check(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $license = MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
            'expires_at' => Carbon::now()->addDays(10),
        ]);

        $token = $user->createToken('test-token', ['maxtree:access'])->plainTextToken;

        // Admin updates expiration date to the past
        $license->update(['expires_at' => Carbon::now()->subMinute()]);

        // Next status check denies access
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/maxtree/license/status')
            ->assertStatus(403)
            ->assertJson(['error_code' => 'LICENSE_EXPIRED']);
    }

    /**
     * Test 16: Logout revokes the current token.
     */
    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $token = $user->createToken('test-token', ['maxtree:access'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/maxtree/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertCount(0, $user->tokens);
    }

    /**
     * Test 17: Revoked token cannot access protected endpoints.
     */
    public function test_revoked_token_cannot_access_protected_endpoints(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $token = $user->createToken('test-token', ['maxtree:access'])->plainTextToken;

        // Logout revokes token
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/maxtree/auth/logout');

        auth()->forgetGuards();

        // Subsequent status check fails with 401
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/maxtree/license/status')
            ->assertStatus(401);
    }

    /**
     * Test 18: Rate limiting throttles excessive login attempts.
     */
    public function test_login_rate_limiting_works(): void
    {
        $user = User::factory()->create([
            'email' => 'rate@academy.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        // First 5 attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/maxtree/auth/login', [
                'email' => 'rate@academy.com',
                'password' => 'WrongPassword',
            ]);
        }

        // 6th attempt should be rate limited with HTTP 429
        $response = $this->postJson('/api/v1/maxtree/auth/login', [
            'email' => 'rate@academy.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(429);
    }

    /**
     * Test 19: Incorrect token abilities do not grant Maxtree access.
     */
    public function test_incorrect_token_abilities_do_not_grant_maxtree_access(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        // Issue token with wrong scope 'web:access' instead of 'maxtree:access'
        $token = $user->createToken('wrong-scope-token', ['web:access'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/maxtree/license/status');

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'UNAUTHORIZED',
            ]);
    }
}
