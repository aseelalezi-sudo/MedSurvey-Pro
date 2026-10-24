<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ResponseController;
use App\Http\Controllers\Api\SurveyController as SurveyApiController;
use App\Http\Middleware\EnsureApiUserIsActive;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('api.health');

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', HealthController::class)->name('health');

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', EnsureApiUserIsActive::class])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        // One call returns the collector, active surveys and all lists the
        // mobile form needs. The app caches this response for offline use.
        Route::get('/bootstrap', BootstrapController::class)->name('bootstrap');

        Route::get('/surveys', [SurveyApiController::class, 'index'])->name('surveys.index');
        Route::get('/surveys/{id}', [SurveyApiController::class, 'show'])->name('surveys.show');

        Route::post('/responses', [ResponseController::class, 'store'])
            ->middleware('throttle:120,1')
            ->name('responses.store');
    });
});
