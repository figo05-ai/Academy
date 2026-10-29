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

use App\Http\Controllers\MaxtreeDownloadController;

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

// Maxtree Tool Installer Public Download Route
Route::get('/maxtree/download', [MaxtreeDownloadController::class, 'download'])->name('maxtree.download');

// AutoCAD Tool Installer Public Download Routes
Route::get('/autocad/download', [\App\Http\Controllers\AutocadDownloadController::class, 'download'])->name('autocad.download');
Route::get('/maktabat/download', [\App\Http\Controllers\AutocadDownloadController::class, 'download'])->name('maktabat.download');

Route::middleware('auth')->group(function () {
    Route::get('/sketchup/authorize', [\App\Http\Controllers\SketchupAuthorizationController::class, 'show'])->name('sketchup.authorize');
    Route::post('/sketchup/authorize', [\App\Http\Controllers\SketchupAuthorizationController::class, 'approve'])->middleware('throttle:10,1')->name('sketchup.approve');
});
Route::middleware(['auth', AdminMiddleware::class])->group(function () {
    Route::get('/admin/users/{user}/sketchup-license/edit', fn (\App\Models\User $user) => view('Admin.users.sketchup', compact('user')))->name('admin.users.sketchup.edit');
    Route::get('/admin/users/{user}/sketchup-license', [\App\Http\Controllers\Admin\AdminSketchupLicenseController::class, 'getSketchupLicense'])->name('admin.users.sketchup.get');
    Route::put('/admin/users/{user}/sketchup-license', [\App\Http\Controllers\Admin\AdminSketchupLicenseController::class, 'updateSketchupLicense'])->name('admin.users.sketchup.update');
    Route::get('/admin/users/{user}/autocad-license', [\App\Http\Controllers\Admin\AdminAutocadLicenseController::class, 'getAutocadLicense'])->name('admin.users.autocad.get');
    Route::put('/admin/users/{user}/autocad-license', [\App\Http\Controllers\Admin\AdminAutocadLicenseController::class, 'updateAutocadLicense'])->name('admin.users.autocad.update');
});

Route::get('/sketchup/download', [\App\Http\Controllers\SketchupDownloadController::class, 'download'])->name('sketchup.download');

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
    Route::get('/admin/users/{user}/maxtree-license', [AdminUserController::class, 'getMaxtreeLicense'])->name('admin.users.maxtree.get');
    Route::put('/admin/users/{user}/maxtree-license', [AdminUserController::class, 'updateMaxtreeLicense'])->name('admin.users.maxtree.update');
    Route::put('/admin/users/{user}/courses', [AdminUserController::class, 'updateUserCourses'])->name('admin.users.courses.update');
    Route::post('/admin/users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('admin.users.toggle_status');
    Route::put('/admin/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
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
