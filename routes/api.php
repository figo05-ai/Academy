<?php

use App\Http\Controllers\Api\V1\MaxtreeAuthController;
use App\Http\Controllers\MaxtreeDownloadController;
use App\Http\Controllers\SketchupDownloadController;
use App\Http\Middleware\EnsureMaxtreeTokenAbility;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Maxtree Tool API Routes (V1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1/maxtree')->group(function () {
    // Installer download route
    Route::get('/download', [MaxtreeDownloadController::class, 'download'])->name('api.v1.maxtree.download');

    // Public authentication route (rate limited to 5 login attempts per minute)
    Route::post('/auth/login', [MaxtreeAuthController::class, 'login'])
        ->middleware('throttle:5,1');

    // Protected license & authentication routes
    Route::middleware(['auth:sanctum', EnsureMaxtreeTokenAbility::class])->group(function () {
        Route::get('/license/status', [MaxtreeAuthController::class, 'status']);
        Route::post('/auth/logout', [MaxtreeAuthController::class, 'logout']);
    });
});

Route::prefix('v1/sketchup')->group(function () {
    // Plugin RBZ download route
    Route::get('/download', [SketchupDownloadController::class, 'download'])->name('api.v1.sketchup.download');

    Route::post('/auth/device', [\App\Http\Controllers\Api\V1\SketchupAuthController::class,'start'])->middleware('throttle:10,1');
    Route::post('/auth/exchange', [\App\Http\Controllers\Api\V1\SketchupAuthController::class,'exchange'])->middleware('throttle:30,1');
    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureSketchupTokenAbility::class, 'throttle:120,1'])->group(function () {
        Route::get('/license/status', [\App\Http\Controllers\Api\V1\SketchupAuthController::class,'status']);
        Route::post('/auth/logout', [\App\Http\Controllers\Api\V1\SketchupAuthController::class,'logout']);
    });
});

// Top-level API download aliases
Route::get('/sketchup/download', [SketchupDownloadController::class, 'download'])->name('api.sketchup.download');
Route::get('/maxtree/download', [MaxtreeDownloadController::class, 'download'])->name('api.maxtree.download');

