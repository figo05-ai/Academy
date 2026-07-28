<?php

namespace App\Services\UserServices;
use App\Models\User;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;
use App\Models\Session;
class UserLifeCycleService
{

    public function getDashboardStats(User $user)
    {
        // First, check if the user account itself is active.
        if (! $user->is_active) {
            return [
                'activeCourses' => collect(),
                'totalEnrolledCourses' => 0,
                'overallProgressPercentage' => 0,
                'completedAssignments' => 0
            ];
        }

        $activeCourses = $user->courses()->wherePivot('status', 'active')
            ->with(['sessions' => function ($query) use ($user) {
                $query->with(['users' => function ($q) use ($user) {
                    $q->where('user_id', $user->id)->withPivot('is_completed');
                }]);
            }])
            ->get();

        $totalEnrolledCourses = $activeCourses->count();
        $totalSessions = 0;
        $completedSessions = 0;
        $completedAssignments = 0;

        foreach ($activeCourses as $course) {
            /** @var \App\Models\Course $course */
            $totalSessions += $course->sessions->count();
            foreach ($course->sessions as $session) {
                $userSessionPivot = $session->users->first();
                if ($userSessionPivot && $userSessionPivot->pivot->is_completed) {
                    $completedSessions++;
                    $completedAssignments++;
                }
            }
        }

        $overallProgressPercentage = ($totalSessions > 0) ? round(($completedSessions / $totalSessions) * 100) : 0;

        return [
            'activeCourses' => $activeCourses,
            'totalEnrolledCourses' => $totalEnrolledCourses,
            'overallProgressPercentage' => $overallProgressPercentage,
            'completedAssignments' => $completedAssignments
        ];
    }

    public function exploreCoursesService(){
        // Return the collection directly for clarity
        return Course::latest()->get();
    }

    public function getAvailableCoursesForUser(User $user)
    {
        // Get IDs of courses the user is already enrolled in
        $enrolledCourseIds = $user->courses()->pluck('courses.id');

        // Get all courses that are not in the enrolled list
        return Course::whereNotIn('id', $enrolledCourseIds)
            ->latest()
            ->get();
    }

    public function getUserCourses(User $user){
        return $user->courses()->wherePivot('status', 'active')->get();
    }
    public function getCourseProgressDetails(User $user, Course $course)
    {
        // Also check if the user account is active here.
        if (! $user->is_active) {
            return false;
        }

        $enrollment = $user->courses()->where('course_id', $course->id)->wherePivot('status', 'active')->first();

        if (! $enrollment) {
            return false;
        }

        $course->load(['sessions' => function ($query) use ($user) {
            $query->orderBy('order_number')
                ->with(['users' => function ($q) use ($user) {
                    $q->where('user_id', $user->id)->withPivot('is_completed');
                }]);
        }]);

        $sessions = $course->sessions;
        $totalSessions = $sessions->count();
        $completedSessionsCount = 0;

        // First, determine completion status for all sessions and count them
        foreach ($sessions as $session) {
            $pivot = $session->users->first();
            $session->user_completed = ($pivot && $pivot->pivot->is_completed);
            if ($session->user_completed) {
                $completedSessionsCount++;
            }
        }

        // The next session to be unlocked is at the index equal to the count of completed sessions.
        // e.g., if 3 sessions are complete (indices 0, 1, 2), the next active one is at index 3.
        $unlockedSessionIndex = $completedSessionsCount;

        // Edge case: If all sessions are completed, the active session should be the last one.
        // This also prevents an "out of bounds" error if all are complete.
        if ($unlockedSessionIndex >= $totalSessions && $totalSessions > 0) {
            $unlockedSessionIndex = $totalSessions - 1;
        }

        return [
            'course' => $course,
            'sessions' => $sessions,
            'unlockedSessionIndex' => $unlockedSessionIndex
        ];
    }
    public function completeSession(User $user, Session $session)
    {
        $isEnrolled = $user->courses()
            ->where('course_id', $session->course_id)
            ->wherePivot('status', 'active')
            ->exists();

        if (! $isEnrolled) {
            return false;
        }

        $user->sessions()->syncWithoutDetaching([
            $session->id => ['is_completed' => true],
        ]);

        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Session> $courseSessions */
        $courseSessions = $session->course->sessions()->orderBy('order_number')->get();
        $nextSession = null;

        $currentSessionIndex = $courseSessions->search(function (\App\Models\Session $item) use ($session) {
            return $item->id === $session->id;
        });

        if ($currentSessionIndex !== false && $currentSessionIndex < $courseSessions->count() - 1) {
            $nextSession = $courseSessions[$currentSessionIndex + 1];
        }

        /** @var \App\Models\Session|null $nextSession */
        return [
            'completed_session_id' => $session->id,
            'next_session_id' => $nextSession ? $nextSession->id : null,
        ];
    }
}
