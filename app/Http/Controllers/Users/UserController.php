<?php

namespace App\Http\Controllers\Users;

use App\Models\Course;
use App\Models\Session;
use App\Services\UserServices\UserLifeCycleService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function dashboard(UserLifeCycleService $userService)
    {
        $user = Auth::user();
        $dashboardData = $userService->getDashboardStats($user);

        $dashboardData['availableCourses'] = $userService->getAvailableCoursesForUser($user);

        return view('Admin.users.dashboard', $dashboardData);
    }

    public function exploreCourses(UserLifeCycleService $service)
    {
        $courses = $service->exploreCoursesService();

        return view('Admin.users.explore_courses', ['data' => $courses]);
    }

    public function myCourses(UserLifeCycleService $userLifeCycleService)
    {
        $user = Auth::user();
        $myCourses = $userLifeCycleService->getUserCourses($user);

        return view('Admin.users.my_courses', compact('myCourses'));
    }

    public function showCourseDetails(Course $course, UserLifeCycleService $service)
    {
        $user = Auth::user();
        $courseData = $service->getCourseProgressDetails($user, $course);

        if (! $courseData) {
            return redirect()->route('user.dashboard')->with('error', 'أنت غير مشترك أو اشتراكك غير مفعل في هذا الكورس.');
        }

        return view('Admin.users.course_details', $courseData);
    }

    public function markSessionComplete(Request $request, Session $session, UserLifeCycleService $service)
    {
        $user = Auth::user();
        $result = $service->completeSession($user, $session);

        if (! $result) {
            return response()->json([
                'message' => 'You are not authorized to complete this session.',
                'status' => 'error'
            ], 403);
        }

        return response()->json([
            'message' => 'Session completed successfully!',
            'status' => 'success',
            'completed_session_id' => $result['completed_session_id'],
            'next_session_id' => $result['next_session_id']
        ]);
    }
}
