<?php

namespace Tests\Feature;

use App\Models\AutocadLicense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AutocadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_user_with_active_autocad_license_login_succeeds(): void
    {
        $user = User::factory()->create([
            'email' => 'valid@academy.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        AutocadLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $response = $this->postJson('/api/v1/autocad/auth/login', [
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

    public function test_maktabat_login_alias_succeeds(): void
    {
        $user = User::factory()->create([
            'email' => 'maktabat@academy.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        AutocadLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/maktabat/auth/login', [
            'email' => 'maktabat@academy.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);
    }

    public function test_valid_user_with_missing_autocad_license_is_denied(): void
    {
        $user = User::factory()->create([
            'email' => 'nolicense@academy.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/autocad/auth/login', [
            'email' => 'nolicense@academy.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'LICENSE_NOT_FOUND',
            ]);
    }

    public function test_valid_user_with_inactive_autocad_license_is_denied(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@academy.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        AutocadLicense::create([
            'user_id' => $user->id,
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/autocad/auth/login', [
            'email' => 'inactive@academy.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'LICENSE_INACTIVE',
            ]);
    }

    public function test_valid_user_with_expired_autocad_license_is_denied(): void
    {
        $user = User::factory()->create([
            'email' => 'expired@academy.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        AutocadLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $response = $this->postJson('/api/v1/autocad/auth/login', [
            'email' => 'expired@academy.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'LICENSE_EXPIRED',
            ]);
    }

    public function test_invalid_password_is_denied(): void
    {
        $user = User::factory()->create([
            'email' => 'user@academy.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);

        AutocadLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/autocad/auth/login', [
            'email' => 'user@academy.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'INVALID_CREDENTIALS',
            ]);
    }

    public function test_disabled_account_is_denied(): void
    {
        $user = User::factory()->create([
            'email' => 'disabled@academy.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => false,
        ]);

        AutocadLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/autocad/auth/login', [
            'email' => 'disabled@academy.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'ACCOUNT_DISABLED',
            ]);
    }

    public function test_missing_bearer_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/autocad/license/status');
        $response->assertStatus(401);
    }

    public function test_active_token_and_active_license_is_authorized(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        AutocadLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $token = $user->createToken('test-token', ['autocad:access'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/autocad/license/status');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'authorized' => true,
                'user' => ['id' => $user->id],
            ]);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        AutocadLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $token = $user->createToken('test-token', ['autocad:access'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/autocad/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertCount(0, $user->tokens);
    }

    public function test_incorrect_token_abilities_do_not_grant_autocad_access(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        AutocadLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $token = $user->createToken('wrong-scope-token', ['other:access'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/autocad/license/status');

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'error_code' => 'UNAUTHORIZED',
            ]);
    }

    public function test_autocad_device_flow_pairing_and_exchange(): void
    {
        $verifier = bin2hex(random_bytes(32));
        $challenge = hash('sha256', $verifier);

        $startRes = $this->postJson('https://localhost/api/v1/autocad/auth/device', [
            'challenge' => $challenge,
            'remember' => true,
        ]);

        $startRes->assertStatus(200)
            ->assertJsonStructure(['device_code', 'user_code', 'interval', 'expires_in', 'verification_uri']);

        $deviceCode = $startRes->json('device_code');
        $userCode = $startRes->json('user_code');

        // Poll before approval -> 202 AUTHORIZATION_PENDING
        $pollPending = $this->postJson('https://localhost/api/v1/autocad/auth/exchange', [
            'device_code' => $deviceCode,
            'verifier' => $verifier,
        ]);
        $pollPending->assertStatus(202)->assertJson(['error_code' => 'AUTHORIZATION_PENDING']);

        // User logs in and approves on web
        $user = User::factory()->create(['is_active' => true]);
        AutocadLicense::create(['user_id' => $user->id, 'is_active' => true]);

        $this->actingAs($user)
            ->get('/autocad/authorize?code=' . $userCode)
            ->assertStatus(200)
            ->assertSee($userCode);

        $this->actingAs($user)
            ->post('/autocad/authorize', ['code' => $userCode])
            ->assertStatus(200)
            ->assertSee('تمت الموافقة');

        // Exchange succeeds after approval
        $exchangeRes = $this->postJson('https://localhost/api/v1/autocad/auth/exchange', [
            'device_code' => $deviceCode,
            'verifier' => $verifier,
        ]);

        $exchangeRes->assertStatus(200)
            ->assertJsonStructure(['status', 'token', 'token_type', 'expires_at'])
            ->assertJson(['status' => 'success']);

        $token = $exchangeRes->json('token');
        $this->assertNotEmpty($token);

        // Reset web session guard so Sanctum evaluates the bearer token cleanly
        auth()->forgetGuards();

        // Status check with issued token
        $statusRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('https://localhost/api/v1/autocad/license/status');

        $statusRes->assertStatus(200)
            ->assertJson([
                'authorized' => true,
                'product' => 'technical-office-autocad',
            ]);
    }
}
