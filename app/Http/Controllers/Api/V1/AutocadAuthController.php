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
     * Check if request is secure or running in a local/testing/development environment.
     */
    protected function isConnectionAllowed(Request $request): bool
    {
        return $request->isSecure()
            || app()->environment('local', 'testing')
            || ! app()->environment('production')
            || in_array($request->getHost(), ['localhost', '127.0.0.1', '::1', '10.0.2.2']);
    }

    /**
     * Start device pairing flow for AutoCAD plugin.
     */
    public function start(Request $request): JsonResponse
    {
        abort_unless($this->isConnectionAllowed($request), 400);

        // Accept challenge in snake_case, camelCase, or PascalCase (C# client compatibility)
        $challenge = (string) ($request->input('challenge') ?? $request->input('Challenge'));
        $remember = $request->input('remember') ?? $request->input('Remember', false);
        $remember = filter_var($remember, FILTER_VALIDATE_BOOLEAN);

        if (! preg_match('/\A[a-f0-9]{64}\z/i', $challenge)) {
            return $this->reply([
                'status' => 'error',
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'The challenge must be a 64-character hexadecimal SHA-256 string.',
            ], 422);
        }

        $device = bin2hex(random_bytes(32));
        $code = strtoupper(bin2hex(random_bytes(5)));

        DB::table('autocad_device_requests')->insert([
            'device_hash' => hash('sha256', $device),
            'code_hash' => hash('sha256', $code),
            'challenge' => strtolower($challenge),
            'remember' => $remember,
            'expires_at' => now()->addMinutes(config('autocad.device_minutes', 5)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $verificationUri = route('autocad.authorize', ['code' => $code]);

        if (! app()->environment('testing')) {
            if (str_contains($request->getHost(), 'the-technical-office-for-landscaping-works.com') || app()->environment('production')) {
                $verificationUri = 'https://the-technical-office-for-landscaping-works.com/autocad/authorize?code=' . $code;
            }
        }

        return $this->reply([
            'status' => 'success',
            'device_code' => $device,
            'user_code' => $code,
            'interval' => 5,
            'expires_in' => config('autocad.device_minutes', 5) * 60,
            'verification_uri' => $verificationUri,
        ]);
    }

    /**
     * Poll/exchange device authorization token for AutoCAD plugin.
     */
    public function exchange(Request $request): JsonResponse
    {
        abort_unless($this->isConnectionAllowed($request), 400);

        $deviceCode = (string) ($request->input('device_code') ?? $request->input('deviceCode') ?? $request->input('DeviceCode'));
        $verifier = (string) ($request->input('verifier') ?? $request->input('Verifier'));

        if (! preg_match('/\A[a-f0-9]{64}\z/i', $deviceCode) || ! preg_match('/\A[a-f0-9]{64}\z/i', $verifier)) {
            return $this->reply([
                'status' => 'error',
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Invalid device code or verifier format.',
            ], 422);
        }

        $deviceCode = strtolower($deviceCode);
        $verifier = strtolower($verifier);

        return DB::transaction(function () use ($deviceCode, $verifier) {
            $row = DB::table('autocad_device_requests')
                ->where('device_hash', hash('sha256', $deviceCode))
                ->lockForUpdate()
                ->first();

            if (! $row || $row->consumed_at || Carbon::parse($row->expires_at)->lessThanOrEqualTo(now()) || ! hash_equals($row->challenge, hash('sha256', $verifier))) {
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
        // Support snake_case, camelCase, and PascalCase from C# / .NET clients
        $email = (string) ($request->input('email') ?? $request->input('Email'));
        $password = (string) ($request->input('password') ?? $request->input('Password'));

        $email = strtolower(trim($email));

        if (empty($email) || empty($password)) {
            return $this->reply([
                'status' => 'error',
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Email and password are required.',
            ], 422);
        }

        // Case-insensitive user lookup
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first()
            ?? User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return $this->reply([
                'status' => 'error',
                'authorized' => false,
                'is_licensed' => false,
                'error_code' => 'INVALID_CREDENTIALS',
                'message' => 'The provided credentials do not match our records.',
            ], 401);
        }

        if (! $user->is_active) {
            return $this->reply([
                'status' => 'error',
                'authorized' => false,
                'is_licensed' => false,
                'error_code' => 'ACCOUNT_DISABLED',
                'message' => 'Your account is disabled.',
            ], 403);
        }

        $license = $user->autocadLicense;

        if (! $license) {
            return $this->reply([
                'status' => 'error',
                'authorized' => false,
                'is_licensed' => false,
                'error_code' => 'LICENSE_NOT_FOUND',
                'message' => 'No AutoCAD license record found for this account.',
            ], 403);
        }

        if (! $license->is_active) {
            return $this->reply([
                'status' => 'error',
                'authorized' => false,
                'is_licensed' => false,
                'error_code' => 'LICENSE_INACTIVE',
                'message' => 'Your AutoCAD license is inactive.',
            ], 403);
        }

        if ($license->expires_at !== null && $license->expires_at->isPast()) {
            return $this->reply([
                'status' => 'error',
                'authorized' => false,
                'is_licensed' => false,
                'error_code' => 'LICENSE_EXPIRED',
                'message' => 'Your AutoCAD license has expired.',
            ], 403);
        }

        // Issue Sanctum token scoped specifically for AutoCAD plugin access (expires in 30 days)
        $token = $user->createToken('autocad-plugin-token', ['autocad:access'], now()->addDays(30))->plainTextToken;

        return $this->reply([
            'status' => 'success',
            'authorized' => true,
            'is_licensed' => true,
            'is_active' => true,
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
                'authorized' => false,
                'is_licensed' => false,
                'error_code' => 'ACCOUNT_DISABLED',
                'message' => 'Your account is disabled.',
            ], 403);
        }

        /** @var \App\Models\AutocadLicense|null $license */
        $license = $user->autocadLicense()->first();

        if (! $license) {
            return $this->reply([
                'status' => 'error',
                'authorized' => false,
                'is_licensed' => false,
                'error_code' => 'LICENSE_NOT_FOUND',
                'message' => 'No AutoCAD license record found for this account.',
            ], 403);
        }

        if (! $license->is_active) {
            return $this->reply([
                'status' => 'error',
                'authorized' => false,
                'is_licensed' => false,
                'error_code' => 'LICENSE_INACTIVE',
                'message' => 'Your AutoCAD license is inactive.',
            ], 403);
        }

        if ($license->expires_at !== null && $license->expires_at->isPast()) {
            return $this->reply([
                'status' => 'error',
                'authorized' => false,
                'is_licensed' => false,
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
            'is_licensed' => true,
            'is_active' => (bool) $license->is_active,
            'product' => 'technical-office-autocad',
            'lease_seconds' => max(1, min(30, (int) now()->diffInSeconds($deadline, false))),
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

