<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\Licensing\AutocadLicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AutocadAuthController extends Controller
{
    public function __construct(
        protected AutocadLicenseService $licenseService
    ) {}

    private function reply(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->header('Cache-Control', 'no-store');
    }

    /**
     * Start device pairing flow for AutoCAD plugin.
     */
    public function start(Request $request): JsonResponse
    {
        abort_unless($request->isSecure() || app()->environment('local', 'testing'), 400);

        $data = $request->validate([
            'challenge' => ['required', 'regex:/\A[a-f0-9]{64}\z/'],
            'remember' => ['required', 'boolean'],
        ]);

        $device = bin2hex(random_bytes(32));
        $code = strtoupper(bin2hex(random_bytes(5)));

        DB::table('autocad_device_requests')->insert([
            'device_hash' => hash('sha256', $device),
            'code_hash' => hash('sha256', $code),
            'challenge' => $data['challenge'],
            'remember' => $data['remember'],
            'expires_at' => now()->addMinutes(config('autocad.device_minutes', 5)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->reply([
            'device_code' => $device,
            'user_code' => $code,
            'interval' => 5,
            'expires_in' => config('autocad.device_minutes', 5) * 60,
            'verification_uri' => route('autocad.authorize', ['code' => $code]),
        ]);
    }

    /**
     * Poll/exchange device authorization token for AutoCAD plugin.
     */
    public function exchange(Request $request): JsonResponse
    {
        abort_unless($request->isSecure() || app()->environment('local', 'testing'), 400);

        $data = $request->validate([
            'device_code' => ['required', 'regex:/\A[a-f0-9]{64}\z/'],
            'verifier' => ['required', 'regex:/\A[a-f0-9]{64}\z/'],
        ]);

        return DB::transaction(function () use ($data) {
            $row = DB::table('autocad_device_requests')
                ->where('device_hash', hash('sha256', $data['device_code']))
                ->lockForUpdate()
                ->first();

            if (! $row || $row->consumed_at || Carbon::parse($row->expires_at)->lessThanOrEqualTo(now()) || ! hash_equals($row->challenge, hash('sha256', $data['verifier']))) {
                return $this->reply(['error_code' => 'DEVICE_EXPIRED'], 400);
            }

            if (! $row->user_id) {
                return $this->reply(['error_code' => 'AUTHORIZATION_PENDING'], 202);
            }

            DB::table('autocad_device_requests')->where('id', $row->id)->update(['consumed_at' => now()]);

            $user = User::find($row->user_id);
            if (! $user || ! $user->is_active) {
                return $this->reply(['error_code' => 'ACCOUNT_DISABLED'], 403);
            }

            $expiry = $row->remember ? now()->addDays(config('autocad.remember_days', 30)) : now()->addMinutes(config('session.lifetime', 120));
            $token = $user->createToken('autocad-plugin-token', ['autocad:access'], $expiry);

            return $this->reply([
                'status' => 'success',
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiry->toIso8601String(),
            ]);
        });
    }

    /**
     * Authenticate user with Academy credentials and verify AutoCAD license.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return $this->reply([
                'status' => 'error',
                'error_code' => 'INVALID_CREDENTIALS',
                'message' => 'The provided credentials do not match our records.',
            ], 401);
        }

        if (! $user->is_active) {
            return $this->reply([
                'status' => 'error',
                'error_code' => 'ACCOUNT_DISABLED',
                'message' => 'Your account is disabled.',
            ], 403);
        }

        $license = $user->autocadLicense;

        if (! $license) {
            return $this->reply([
                'status' => 'error',
                'error_code' => 'LICENSE_NOT_FOUND',
                'message' => 'No AutoCAD license record found for this account.',
            ], 403);
        }

        if (! $license->is_active) {
            return $this->reply([
                'status' => 'error',
                'error_code' => 'LICENSE_INACTIVE',
                'message' => 'Your AutoCAD license is inactive.',
            ], 403);
        }

        if ($license->expires_at !== null && $license->expires_at->isPast()) {
            return $this->reply([
                'status' => 'error',
                'error_code' => 'LICENSE_EXPIRED',
                'message' => 'Your AutoCAD license has expired.',
            ], 403);
        }

        // Issue Sanctum token scoped specifically for AutoCAD plugin access (expires in 30 days)
        $token = $user->createToken('autocad-plugin-token', ['autocad:access'], now()->addDays(30))->plainTextToken;

        return $this->reply([
            'status' => 'success',
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in_days' => 30,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'license' => [
                'is_active' => (bool) $license->is_active,
                'expires_at' => $license->expires_at ? $license->expires_at->toIso8601String() : null,
            ],
        ], 200);
    }

    /**
     * Verify current token and live AutoCAD license status.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            return $this->reply([
                'status' => 'error',
                'error_code' => 'ACCOUNT_DISABLED',
                'message' => 'Your account is disabled.',
            ], 403);
        }

        /** @var \App\Models\AutocadLicense|null $license */
        $license = $user->autocadLicense()->first();

        if (! $license) {
            return $this->reply([
                'status' => 'error',
                'error_code' => 'LICENSE_NOT_FOUND',
                'message' => 'No AutoCAD license record found for this account.',
            ], 403);
        }

        if (! $license->is_active) {
            return $this->reply([
                'status' => 'error',
                'error_code' => 'LICENSE_INACTIVE',
                'message' => 'Your AutoCAD license is inactive.',
            ], 403);
        }

        if ($license->expires_at !== null && $license->expires_at->isPast()) {
            return $this->reply([
                'status' => 'error',
                'error_code' => 'LICENSE_EXPIRED',
                'message' => 'Your AutoCAD license has expired.',
            ], 403);
        }

        $token = $user->currentAccessToken();
        $deadline = now()->addSeconds(config('autocad.lease_seconds', 30));

        foreach ([$license->expires_at, $token?->expires_at, config('sanctum.expiration') && $token ? $token->created_at->addMinutes(config('sanctum.expiration')) : null] as $expiry) {
            if ($expiry && $expiry->lessThan($deadline)) {
                $deadline = $expiry;
            }
        }

        return $this->reply([
            'status' => 'success',
            'authorized' => true,
            'product' => 'technical-office-autocad',
            'lease_seconds' => max(0, (int) now()->diffInSeconds($deadline, false)),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'license' => [
                'is_active' => (bool) $license->is_active,
                'expires_at' => $license->expires_at ? $license->expires_at->toIso8601String() : null,
            ],
            'expires_at' => $license->expires_at ? $license->expires_at->toIso8601String() : null,
        ], 200);
    }

    /**
     * Revoke current AutoCAD API token.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return $this->reply([
            'status' => 'success',
            'revoked' => true,
            'message' => 'AutoCAD token revoked successfully.',
        ], 200);
    }
}
