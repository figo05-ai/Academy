<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\UserRequests\UpdateUserDetailRequest;
use App\Http\Requests\UserRequests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserServices\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class AdminUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, UserService $UserService)
    {
        $data = $UserService->UserIndex($request->all());

        return view('Admin.users.index', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserDetailRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        $user->update($data);

        return response()->json([
            'success' => 'تم تحديث بيانات المستخدم بنجاح!',
            'user' => new UserResource($user->fresh())
        ]);
    }

    /**
     * Update the courses a user is enrolled in.
     */
    public function updateUserCourses(UserRequest $request, User $user, UserService $UserService)
    {
        $cleanData = $request->validated();
        $UserService->updateUser($cleanData, $user);

        return response()->json(['success' => 'User courses have been successfully updated!']);
    }

    /**
     * Toggle the active status of a user.
     */
    public function toggleStatus(User $user): JsonResponse
    {
        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'success' => 'تم تحديث حالة المستخدم بنجاح!',
            'is_active' => $user->is_active,
        ]);
    }

    /**
     * Get the Maxtree license details for a user.
     */
    public function getMaxtreeLicense(User $user): JsonResponse
    {
        /** @var \App\Models\MaxtreeLicense|null $license */
        $license = $user->maxtreeLicense;

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
                'created_at' => $license->created_at->format('Y-m-d H:i'),
            ] : null,
        ]);
    }

    /**
     * Activate, deactivate, or update the Maxtree license for a user.
     */
    public function updateMaxtreeLicense(Request $request, User $user): JsonResponse
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

        /** @var \App\Models\MaxtreeLicense $license */
        $license = $user->maxtreeLicense()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'is_active' => (bool) $validated['is_active'],
                'expires_at' => $expiresAt,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return response()->json([
            'success' => 'تم تحديث ترخيص Maxtree بنجاح!',
            'license' => [
                'id' => $license->id,
                'is_active' => (bool) $license->is_active,
                'expires_at' => $license->expires_at ? $license->expires_at->format('Y-m-d\TH:i') : null,
                'notes' => $license->notes,
            ],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): JsonResponse
    {
        if (auth()->id() === $user->id) {
            return response()->json(['error' => 'لا يمكنك حذف حسابك الخاص.'], 403);
        }

        $user->courses()->detach();
        $user->delete();

        return response()->json(['success' => 'تم حذف المستخدم بنجاح.']);
    }
}
