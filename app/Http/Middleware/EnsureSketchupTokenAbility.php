<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureSketchupTokenAbility
{
    /**
     * Handle an incoming request.
     *
     * Ensures the request is authenticated via a genuine Sanctum PersonalAccessToken
     * with the required 'sketchup:access' token ability.
     * Web session cookies and transient tokens are explicitly rejected.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user || ! $token || ! ($token instanceof PersonalAccessToken)) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'UNAUTHORIZED',
                'message' => 'A valid Sketchup API Bearer Token is required.',
            ], 401);
        }

        if (! $token->can('sketchup:access')) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'UNAUTHORIZED',
                'message' => 'Token does not have required sketchup:access ability.',
            ], 403);
        }

        return $next($request);
    }
}
