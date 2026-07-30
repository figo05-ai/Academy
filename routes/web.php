<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminCourseController;
use App\Http\Controllers\Admin\AdminSessionController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Authentication\AuthController;
use App\Http\Controllers\Authentication\GoogleAuthController;
use App\Http\Controllers\Users\UserController;
use App\Http\Middleware\AdminMiddleware;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;

// Homepage Route
Route::get('/', function () {
    $projects = Project::latest()->get();
    $setting = Setting::first() ?? new Setting;
    $courses = \App\Models\Course::latest()->get();
    $userEnrolledCourseIds = [];
    if (auth()->check()) {
        $userEnrolledCourseIds = auth()->user()->courses()->where('course_user.status', 'active')->pluck('courses.id')->toArray();
    }

    return view('index', compact('setting', 'projects', 'courses', 'userEnrolledCourseIds'));
});

// Authentication Routes
Route::get('/register', function () {
    return view('Login.register');
})->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');

// Login/Logout Routes
Route::get('/login', function () {
    return view('Login.login');
})->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Admin Routes (protected by auth and AdminMiddleware)
Route::middleware(['auth', AdminMiddleware::class])->group(function () {

    // General Admin Routes
    Route::get('/admin', [AdminController::class, 'index']);
    Route::put('/admin/settings', [AdminController::class, 'updateSettings']);
    Route::post('/admin/projects', [AdminController::class, 'storeProject']);
    // Corrected route model binding for consistency
    Route::delete('/admin/projects/{project}', [AdminController::class, 'destroyProject'])->name('admin.projects.destroy');
    // Course Deletion Route
    Route::delete('/admin/courses/{course}', [AdminCourseController::class, 'destroy'])->name('admin.courses.destroy');

    // Admin User Management Routes
    Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::put('/admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::put('/admin/users/{user}/courses', [AdminUserController::class, 'updateUserCourses'])->name('admin.users.courses.update');
    Route::post('/admin/users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('admin.users.toggle_status');
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');

    // Admin Course Management
    Route::post('/admin/courses', [AdminCourseController::class, 'store'])->name('admin.courses.store');
    Route::put('/admin/courses/{course}', [AdminCourseController::class, 'update'])->name('admin.courses.update');
    Route::get('/admin/courses/{course}/sessions', [AdminCourseController::class, 'getSessions'])->name('admin.courses.sessions');

    // Admin Session Management
    Route::post('/admin/courses/{course}/sessions', [AdminSessionController::class, 'store'])->name('admin.sessions.store');
    Route::put('/admin/sessions/{session}', [AdminSessionController::class, 'update'])->name('admin.sessions.update');
    Route::delete('/admin/sessions/{session}', [AdminSessionController::class, 'destroy'])->name('admin.sessions.destroy');
    Route::post('/admin/sessions/reorder', [AdminSessionController::class, 'reorder'])->name('admin.sessions.reorder');
});

// Authenticated User Routes
Route::middleware(['auth'])->prefix('user')->group(function () {
    Route::get('/dashboard', [UserController::class, 'dashboard'])->name('user.dashboard');
    Route::get('/explore-courses', [UserController::class, 'exploreCourses'])->name('user.explore_courses');
    Route::get('/my-courses', [UserController::class, 'myCourses'])->name('user.my_courses');
    Route::get('/courses/{course}', [UserController::class, 'showCourseDetails'])->name('user.course_details');
    Route::post('/sessions/{session}/complete', [UserController::class, 'markSessionComplete'])->name('user.sessions.complete');
});






Route::fallback(function () {
    return view('Errors.custom-404');
});
