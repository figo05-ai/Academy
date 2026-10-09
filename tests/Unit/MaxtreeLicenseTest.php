<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\MaxtreeLicense;
use App\Models\User;
use App\Services\Licensing\MaxtreeLicenseService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaxtreeLicenseTest extends TestCase
{
    use RefreshDatabase;

    private MaxtreeLicenseService $licenseService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->licenseService = new MaxtreeLicenseService();
    }

    /**
     * Test 1: User with no license is denied.
     */
    public function test_user_with_no_license_is_denied(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $this->assertNull($user->maxtreeLicense);
        $this->assertFalse($this->licenseService->isUserLicensed($user));
    }

    /**
     * Test 2: User with inactive license is denied.
     */
    public function test_user_with_inactive_license_is_denied(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => false,
        ]);

        $user->refresh();

        $this->assertFalse($this->licenseService->isUserLicensed($user));
    }

    /**
     * Test 3: User with active non-expired license is allowed.
     */
    public function test_user_with_active_non_expired_license_is_allowed(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $user->refresh();

        $this->assertTrue($this->licenseService->isUserLicensed($user));
    }

    /**
     * Test 4: User with expired license is denied.
     */
    public function test_user_with_expired_license_is_denied(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $user->refresh();

        $this->assertFalse($this->licenseService->isUserLicensed($user));
    }

    /**
     * Test 5: User with active license and no courses is allowed.
     */
    public function test_user_with_active_license_and_no_courses_is_allowed(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $user->refresh();

        $this->assertCount(0, $user->courses);
        $this->assertTrue($this->licenseService->isUserLicensed($user));
    }

    /**
     * Test 6: User with active courses and no Maxtree license is denied.
     */
    public function test_user_with_active_courses_and_no_maxtree_license_is_denied(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $course = Course::create([
            'title' => 'Sample Architectural Course',
            'description' => 'Course description',
            'price' => 100,
        ]);

        $user->courses()->attach($course->id, ['status' => 'active']);

        $user->refresh();

        $this->assertCount(1, $user->courses);
        $this->assertNull($user->maxtreeLicense);
        $this->assertFalse($this->licenseService->isUserLicensed($user));
    }

    /**
     * Test 7: Creating a license without explicitly setting is_active defaults to false (inactive).
     */
    public function test_creating_license_without_explicit_activation_defaults_to_inactive(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $license = MaxtreeLicense::create([
            'user_id' => $user->id,
        ]);

        $license->refresh();

        $this->assertFalse($license->is_active);
        $this->assertFalse($this->licenseService->isUserLicensed($user));
    }

    /**
     * Test 8: Duplicate license records for the same user are prevented at database level.
     */
    public function test_duplicate_license_records_for_same_user_are_prevented(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    /**
     * Test 9: Existing course activation data remains unchanged and independent.
     */
    public function test_existing_course_activation_data_remains_unchanged(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $course = Course::create([
            'title' => '3ds Max Visualization Course',
            'description' => 'Test course',
            'price' => 200,
        ]);

        $user->courses()->attach($course->id, ['status' => 'active']);

        // Attach a Maxtree license
        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $user->refresh();

        // Verify course pivot status remains 'active'
        $this->assertEquals('active', $user->courses()->first()->pivot->status);
        $this->assertTrue($this->licenseService->isUserLicensed($user));
    }

    /**
     * Test 10: Existing account-level security rules are respected (disabled user is denied despite active license).
     */
    public function test_existing_account_level_security_rules_are_respected(): void
    {
        $user = User::factory()->create([
            'is_active' => false, // Account suspended/disabled globally
        ]);

        MaxtreeLicense::create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $user->refresh();

        // Global security rule overrides license active status
        $this->assertFalse($this->licenseService->isUserLicensed($user));
    }

    /**
     * Extra Test: Null user evaluates to false.
     */
    public function test_null_user_evaluates_to_denied(): void
    {
        $this->assertFalse($this->licenseService->isUserLicensed(null));
    }
}
