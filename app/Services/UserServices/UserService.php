<?php

namespace App\Services\UserServices;

use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserService
{
    public function UserIndex(array $data)
    {
        $query = User::query()->with('courses')->where('is_admin', false);

        if (! empty($data['search'])) {
            $search = $data['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        return [
            'users' => $query->latest()->paginate(15),
            'allCourses' => Course::all(),
        ];
    }

    public function updateUser(array $data, User $user)
    {
        $courseIds = $data['course_ids'] ?? [];

        // Prepare the data for sync, ensuring the pivot 'status' is set to 'active'.
        $syncData = [];
        foreach ($courseIds as $courseId) {
            $syncData[$courseId] = ['status' => 'active'];
        }
        $user->courses()->sync($syncData);
    }

    public function createUser(array $data)
    {
        return User::create($data);
    }

    public function Login(array $data)
    {
        return Auth::attempt($data);
    }
}
