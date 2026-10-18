<?php
namespace App\Http\Controllers\Api\V1;
use App\Models\User;
use App\Services\Licensing\SketchupLicenseService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
class SketchupAuthController extends Controller {
    private function reply(array $data, int $status = 200) {
        return response()->json($data, $status)->header('Cache-Control', 'no-store');
    }
    public function start(Request $request) {
        abort_unless($request->isSecure(), 400);
        $data = $request->validate(['challenge'=>['required','regex:/\A[a-f0-9]{64}\z/'], 'remember'=>['required','boolean']]);
        $device = bin2hex(random_bytes(32));
        $code = strtoupper(bin2hex(random_bytes(5)));
        DB::table('sketchup_device_requests')->insert([
            'device_hash'=>hash('sha256',$device), 'code_hash'=>hash('sha256',$code),
            'challenge'=>$data['challenge'], 'remember'=>$data['remember'],
            'expires_at'=>now()->addMinutes(config('sketchup.device_minutes')), 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        // The browser receives only a pairing code, never a device secret or bearer token.
        return $this->reply(['device_code'=>$device, 'user_code'=>$code, 'interval'=>5,
            'expires_in'=>config('sketchup.device_minutes')*60,
            'verification_uri'=>route('sketchup.authorize', ['code'=>$code])]);
    }
    public function exchange(Request $request) {
        abort_unless($request->isSecure(), 400);
        $data=$request->validate(['device_code'=>['required','regex:/\A[a-f0-9]{64}\z/'], 'verifier'=>['required','regex:/\A[a-f0-9]{64}\z/']]);
        return DB::transaction(function () use ($data) {
            $row=DB::table('sketchup_device_requests')->where('device_hash',hash('sha256',$data['device_code']))->lockForUpdate()->first();
            if (!$row || $row->consumed_at || Carbon::parse($row->expires_at)->lessThanOrEqualTo(now()) || !hash_equals($row->challenge,hash('sha256',$data['verifier']))) {
                return $this->reply(['error_code'=>'DEVICE_EXPIRED'],400);
            }
            if (!$row->user_id) return $this->reply(['error_code'=>'AUTHORIZATION_PENDING'],202);
            DB::table('sketchup_device_requests')->where('id',$row->id)->update(['consumed_at'=>now()]);
            $user=User::find($row->user_id);
            if (!$user || !$user->is_active) return $this->reply(['error_code'=>'ACCOUNT_DISABLED'],403);
            $expiry=$row->remember ? now()->addDays(config('sketchup.remember_days')) : now()->addMinutes(config('session.lifetime'));
            $token=$user->createToken('sketchup-plugin-token',['sketchup:access'],$expiry);
            return $this->reply(['token'=>$token->plainTextToken,'expires_at'=>$expiry->toIso8601String()]);
        });
    }
    public function status(Request $request, SketchupLicenseService $licenses) {
        abort_unless($request->isSecure(),400);
        $user=$request->user();
        if ($error=$licenses->error($user)) return $this->reply(['authorized'=>false,'error_code'=>$error],403);
        $license=$user->sketchupLicense()->first();
        $token=$user->currentAccessToken();
        $deadline=now()->addSeconds(config('sketchup.lease_seconds'));
        foreach ([$license->expires_at,$token->expires_at,config('sanctum.expiration') ? $token->created_at->addMinutes(config('sanctum.expiration')) : null] as $expiry) {
            if ($expiry && $expiry->lessThan($deadline)) $deadline=$expiry;
        }
        return $this->reply(['authorized'=>true,'product'=>'technical-office-sketchup',
            'lease_seconds'=>max(0,(int) now()->diffInSeconds($deadline,false)),
            'user'=>['name'=>$user->name],'expires_at'=>$license->expires_at?->toIso8601String()]);
    }
    public function logout(Request $request) {
        abort_unless($request->isSecure(),400);
        $request->user()->currentAccessToken()->delete();
        return $this->reply(['revoked'=>true]);
    }
}
