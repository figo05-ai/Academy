<?php

use App\Http\Controllers\Api\V1\MaxtreeAuthController;
use App\Http\Middleware\EnsureMaxtreeTokenAbility;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Maxtree Tool API Routes (V1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1/maxtree')->group(function () {

    // Public authentication route (rate limited to 5 login attempts per minute)
    Route::post('/auth/login', [MaxtreeAuthController::class, 'login'])
        ->middleware('throttle:5,1');

    // Protected license & authentication routes
    Route::middleware(['auth:sanctum', EnsureMaxtreeTokenAbility::class])->group(function () {
        Route::get('/license/status', [MaxtreeAuthController::class, 'status']);
        Route::post('/auth/logout', [MaxtreeAuthController::class, 'logout']);
    });
});
