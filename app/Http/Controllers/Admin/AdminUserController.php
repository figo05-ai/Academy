<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\User\UpdateUserDetailRequest;
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
