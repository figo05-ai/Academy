<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdminAutocadLicenseController extends Controller
{
    public function getAutocadLicense(User $user): JsonResponse
    {
        /** @var \App\Models\AutocadLicense|null $license */
        $license = $user->autocadLicense;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
            ],
            'license' => $license ? [
                'id' => $license->id,
                'is_active' => (bool) $license->is_active,
                'expires_at' => $license->expires_at ? $license->expires_at->format('Y-m-d\TH:i') : null,
                'notes' => $license->notes,
                'created_at' => $license->created_at ? $license->created_at->format('Y-m-d H:i') : null,
            ] : null,
        ]);
    }

    /**
     * Activate, deactivate, or update the AutoCAD license for a user.
     */
    public function updateAutocadLicense(Request $request, User $user): JsonResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'is_active' => ['required', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'بيانات التحديث غير صالحة.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $expiresAt = ! empty($validated['expires_at']) ? \Illuminate\Support\Carbon::parse($validated['expires_at']) : null;

        /** @var \App\Models\AutocadLicense $license */
        $license = $user->autocadLicense()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'is_active' => (bool) $validated['is_active'],
                'expires_at' => $expiresAt,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return response()->json([
            'success' => 'تم تحديث ترخيص AutoCAD بنجاح!',
            'license' => [
                'id' => $license->id,
                'is_active' => (bool) $license->is_active,
                'expires_at' => $license->expires_at ? $license->expires_at->format('Y-m-d\TH:i') : null,
                'notes' => $license->notes,
            ],
        ]);
    }
}
