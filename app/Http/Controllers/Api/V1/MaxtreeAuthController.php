<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Routing\Controller;
use App\Models\User;
use App\Services\Licensing\MaxtreeLicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MaxtreeAuthController extends Controller
{
    public function __construct(
        protected MaxtreeLicenseService $licenseService
    ) {}

    /**
     * Authenticate user with Academy credentials and verify Maxtree license.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'INVALID_CREDENTIALS',
                'message' => 'The provided credentials do not match our records.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'ACCOUNT_DISABLED',
                'message' => 'Your account is disabled.',
            ], 403);
        }

        $license = $user->maxtreeLicense;

        if (! $license) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'LICENSE_NOT_FOUND',
                'message' => 'No Maxtree license record found for this account.',
            ], 403);
        }

        if (! $license->is_active) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'LICENSE_INACTIVE',
                'message' => 'Your Maxtree license is inactive.',
            ], 403);
        }

        if ($license->expires_at !== null && $license->expires_at->isPast()) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'LICENSE_EXPIRED',
                'message' => 'Your Maxtree license has expired.',
            ], 403);
        }

        // Issue Sanctum token scoped specifically for Maxtree plugin access (expires in 30 days)
        $token = $user->createToken('maxtree-plugin-token', ['maxtree:access'], now()->addDays(30))->plainTextToken;

        return response()->json([
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
     * Verify current token and live Maxtree license status.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'ACCOUNT_DISABLED',
                'message' => 'Your account is disabled.',
            ], 403);
        }

        // Always query the fresh license record from the database to prevent stale cache evaluation
        /** @var \App\Models\MaxtreeLicense|null $license */
        $license = $user->maxtreeLicense()->first();

        if (! $license) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'LICENSE_NOT_FOUND',
                'message' => 'No Maxtree license record found for this account.',
            ], 403);
        }

        if (! $license->is_active) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'LICENSE_INACTIVE',
                'message' => 'Your Maxtree license is inactive.',
            ], 403);
        }

        if ($license->expires_at !== null && $license->expires_at->isPast()) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'LICENSE_EXPIRED',
                'message' => 'Your Maxtree license has expired.',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'authorized' => true,
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
     * Revoke current Maxtree API token.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Maxtree token revoked successfully.',
        ], 200);
    }
}
