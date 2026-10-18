<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SketchupAuthTest extends TestCase
{
    use RefreshDatabase;
    private string $base='https://localhost/api/v1/sketchup';
    protected function setUp(): void {
        parent::setUp();
        config(['app.key'=>'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
    }
    private function member(): User { return User::factory()->create(['is_active'=>true,'is_admin'=>false]); }
    private function bearer(User $user, string $ability='sketchup:access', $expiry=null): string {
        return $user->createToken('test',[$ability],$expiry ?? now()->addDays(30))->plainTextToken;
    }
    private function licenseStatus(string $token) {
        // Force a fresh authentication guard just as separate HTTP requests do.
        app('auth')->forgetGuards();
        return $this->withToken($token)->getJson($this->base.'/license/status');
    }
    private function pair(bool $remember=false): array {
        $verifier=bin2hex(random_bytes(32));
        $result=$this->postJson($this->base.'/auth/device',['challenge'=>hash('sha256',$verifier),'remember'=>$remember])->assertOk();
        return [$result->json(),$verifier];
    }
    public function test_no_session_denied(): void { $this->getJson($this->base.'/license/status')->assertUnauthorized(); }
    public function test_product_license_is_required_and_independent(): void {
        $user=$this->member();$token=$this->bearer($user);
        $this->licenseStatus($token)->assertForbidden()->assertJsonPath('error_code','LICENSE_NOT_FOUND');
        $user->maxtreeLicense()->create(['is_active'=>true]);
        $this->licenseStatus($token)->assertForbidden()->assertJsonPath('error_code','LICENSE_NOT_FOUND');
        $user->sketchupLicense()->create(['is_active'=>true]);
        $this->licenseStatus($token)->assertOk()->assertJsonPath('authorized',true)->assertJsonPath('product','technical-office-sketchup');
        $this->assertTrue($user->maxtreeLicense()->first()->is_active);
    }
    public function test_revocation_expiry_and_disabled_account_are_live(): void {
        $user=$this->member();$license=$user->sketchupLicense()->create(['is_active'=>true]);$token=$this->bearer($user);
        $this->licenseStatus($token)->assertOk();
        $license->update(['is_active'=>false]);$this->licenseStatus($token)->assertJsonPath('error_code','LICENSE_INACTIVE');
        $license->update(['is_active'=>true,'expires_at'=>now()->subSecond()]);$this->licenseStatus($token)->assertJsonPath('error_code','LICENSE_EXPIRED');
        $user->update(['is_active'=>false]);$this->licenseStatus($token)->assertJsonPath('error_code','ACCOUNT_DISABLED');
    }
    public function test_token_expiry_and_logout(): void {
        $user=$this->member();$user->sketchupLicense()->create(['is_active'=>true]);
        $this->licenseStatus($this->bearer($user,'sketchup:access',now()->subSecond()))->assertUnauthorized();
        $token=$this->bearer($user);$this->licenseStatus($token)->assertOk();
        app('auth')->forgetGuards();$this->withToken($token)->postJson($this->base.'/auth/logout')->assertOk();
        $this->licenseStatus($token)->assertUnauthorized();
    }
    public function test_maxtree_token_cannot_access_sketchup_and_reverse(): void {
        $user=$this->member();$user->sketchupLicense()->create(['is_active'=>true]);$user->maxtreeLicense()->create(['is_active'=>true]);
        $this->licenseStatus($this->bearer($user,'maxtree:access'))->assertForbidden();
        app('auth')->forgetGuards();$this->withToken($this->bearer($user))->getJson('https://localhost/api/v1/maxtree/license/status')->assertForbidden();
    }
    public function test_browser_session_cannot_replace_sketchup_bearer(): void {
        $this->actingAs($this->member())->getJson($this->base.'/license/status')->assertUnauthorized();
    }
    public function test_pairing_requires_browser_approval_and_verifier_and_is_single_use(): void {
        [$pair,$verifier]=$this->pair();
        $payload=['device_code'=>$pair['device_code'],'verifier'=>$verifier];
        $this->postJson($this->base.'/auth/exchange',$payload)->assertStatus(202);
        $user=$this->member();
        $this->actingAs($user)->get('https://localhost/sketchup/authorize?code='.$pair['user_code'])->assertOk()->assertSee($pair['user_code']);
        $this->post('https://localhost/sketchup/authorize',['code'=>$pair['user_code']])->assertOk();
        $this->post('https://localhost/sketchup/authorize',['code'=>$pair['user_code']])->assertStatus(410);
        $this->postJson($this->base.'/auth/exchange',['device_code'=>$pair['device_code'],'verifier'=>str_repeat('a',64)])->assertStatus(400);
        $response=$this->postJson($this->base.'/auth/exchange',$payload)->assertOk();
        $issued=PersonalAccessToken::findToken($response->json('token'));
        $this->assertSame($user->id,$issued->tokenable_id);
        $this->assertSame(['sketchup:access'],$issued->abilities);
        $this->assertLessThanOrEqual(config('session.lifetime')*60+1,now()->diffInSeconds($issued->expires_at));
        $this->postJson($this->base.'/auth/exchange',$payload)->assertStatus(400);
        $this->assertDatabaseMissing('sketchup_licenses',['user_id'=>$user->id]);
    }
    public function test_expired_pair_cannot_be_approved_or_exchanged(): void {
        [$pair,$verifier]=$this->pair();$this->travel(6)->minutes();
        $this->actingAs($this->member())->post('https://localhost/sketchup/authorize',['code'=>$pair['user_code']])->assertStatus(410);
        $this->postJson($this->base.'/auth/exchange',['device_code'=>$pair['device_code'],'verifier'=>$verifier])->assertStatus(400);
    }
    public function test_remember_me_uses_30_day_policy(): void {
        [$pair,$verifier]=$this->pair(true);
        $this->actingAs($this->member())->post('https://localhost/sketchup/authorize',['code'=>$pair['user_code']])->assertOk();
        $response=$this->postJson($this->base.'/auth/exchange',['device_code'=>$pair['device_code'],'verifier'=>$verifier])->assertOk();
        $token=PersonalAccessToken::findToken($response->json('token'));
        $this->assertEqualsWithDelta(30,now()->diffInDays($token->expires_at),0.001);
    }
    public function test_browser_requires_login_and_csrf(): void {
        [$pair]=$this->pair();
        $url='https://localhost/sketchup/authorize?code='.$pair['user_code'];
        $this->get($url)->assertRedirect();
        $this->actingAs($this->member());
        // Laravel skips CSRF while running unit tests; switch only the environment check.
        $this->app->instance('env','csrf-check');
        $this->post('https://localhost/sketchup/authorize',['code'=>$pair['user_code']])->assertStatus(419);
    }
    public function test_https_is_required(): void {
        $this->postJson('http://localhost/api/v1/sketchup/auth/device',['challenge'=>str_repeat('a',64),'remember'=>false])->assertStatus(400);
    }
    public function test_admin_can_grant_and_revoke_without_changing_maxtree(): void {
        $admin=User::factory()->create(['is_admin'=>true,'is_active'=>true]);$user=$this->member();
        $user->maxtreeLicense()->create(['is_active'=>true]);
        $url='https://localhost/admin/users/'.$user->id.'/sketchup-license';
        $this->actingAs($admin)->putJson($url,['is_active'=>true,'expires_at'=>now()->addDay()->toIso8601String()])->assertOk();
        $this->get($url.'/edit')->assertOk()->assertSee('SketchUp');
        $this->assertTrue($user->sketchupLicense()->first()->is_active);
        $this->putJson($url,['is_active'=>false])->assertOk();
        $this->assertFalse($user->sketchupLicense()->first()->is_active);
        $this->assertTrue($user->maxtreeLicense()->first()->is_active);
        $this->actingAs($user)->putJson($url,['is_active'=>true])->assertForbidden();
    }
    public function test_admin_form_requires_csrf(): void {
        $admin=User::factory()->create(['is_admin'=>true]);$user=$this->member();
        $this->actingAs($admin);$this->app->instance('env','csrf-check');
        $this->putJson('https://localhost/admin/users/'.$user->id.'/sketchup-license',['is_active'=>true])->assertStatus(419);
    }
}
