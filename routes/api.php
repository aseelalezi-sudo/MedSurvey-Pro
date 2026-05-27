<?php

use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\ErrorLogController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MonitoringController;
use App\Http\Controllers\Api\ResponseController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SurveyController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:api');
});

Route::prefix('settings')->group(function (): void {
    Route::get('/', [SettingsController::class, 'show']);
    Route::put('/', [SettingsController::class, 'update'])->middleware(['auth:api', 'role:super_admin,admin']);
    Route::get('/usage-check', [SettingsController::class, 'usageCheck'])->middleware(['auth:api', 'role:super_admin,admin']);
});

Route::prefix('surveys')->group(function (): void {
    Route::get('/', [SurveyController::class, 'index']);
    Route::post('/', [SurveyController::class, 'store'])->middleware(['auth:api', 'role:super_admin,admin']);
    Route::put('/{id}', [SurveyController::class, 'update'])->middleware(['auth:api', 'role:super_admin,admin']);
    Route::delete('/{id}', [SurveyController::class, 'destroy'])->middleware(['auth:api', 'role:super_admin,admin']);
});

Route::prefix('responses')->group(function (): void {
    Route::post('/', [ResponseController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/export', [ResponseController::class, 'export'])->middleware('auth:api');
    Route::get('/', [ResponseController::class, 'index'])->middleware('auth:api');
    Route::get('/stats', [ResponseController::class, 'stats'])->middleware('auth:api');
    Route::get('/predictive', [ResponseController::class, 'predictive'])->middleware('auth:api');
    Route::get('/{id}', [ResponseController::class, 'show'])->middleware('auth:api');
});

Route::prefix('tickets')->middleware('auth:api')->group(function (): void {
    Route::get('/', [TicketController::class, 'index']);
    Route::patch('/{id}', [TicketController::class, 'update']);
    Route::delete('/{id}', [TicketController::class, 'destroy'])->middleware('role:super_admin,admin');
});

Route::prefix('users')->middleware('auth:api')->group(function (): void {
    Route::get('/', [UserController::class, 'index'])->middleware('role:super_admin,admin');
    Route::post('/', [UserController::class, 'store'])->middleware('role:super_admin,admin');
    Route::put('/{id}', [UserController::class, 'update'])->middleware('role:super_admin,admin');
    Route::patch('/{id}/password', [UserController::class, 'changePassword']);
    Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('role:super_admin,admin');
    Route::patch('/{id}/toggle', [UserController::class, 'toggle'])->middleware('role:super_admin,admin');
});

Route::prefix('audit')->middleware('auth:api')->group(function (): void {
    Route::post('/events', [AuditController::class, 'recordEvent']);
    Route::get('/stats', [AuditController::class, 'stats'])->middleware('role:super_admin,admin');
    Route::get('/', [AuditController::class, 'index'])->middleware('role:super_admin,admin');
});

Route::prefix('error-logs')->group(function (): void {
    Route::post('/client', [ErrorLogController::class, 'client']);
    Route::middleware(['auth:api', 'role:super_admin,admin'])->group(function (): void {
        Route::get('/', [ErrorLogController::class, 'index']);
        Route::get('/stats', [ErrorLogController::class, 'stats']);
        Route::patch('/{id}', [ErrorLogController::class, 'update']);
    });
    Route::delete('/', [ErrorLogController::class, 'clearAll'])->middleware(['auth:api', 'role:super_admin']);
    Route::delete('/{id}', [ErrorLogController::class, 'destroy'])->middleware(['auth:api', 'role:super_admin']);
});

Route::prefix('monitoring')->middleware(['auth:api', 'role:super_admin,admin'])->group(function (): void {
    Route::get('/health', [MonitoringController::class, 'health']);
});

Route::prefix('backups')->middleware(['auth:api', 'role:super_admin,admin'])->group(function (): void {
    Route::get('/', [BackupController::class, 'index']);
    Route::post('/', [BackupController::class, 'create']);
    Route::get('/{filename}/verify', [BackupController::class, 'verify']);
    Route::get('/{filename}/download', [BackupController::class, 'download'])->middleware('role:super_admin');
    Route::delete('/{filename}', [BackupController::class, 'destroy'])->middleware('role:super_admin');
    Route::post('/{filename}/restore', [BackupController::class, 'restore'])->middleware('role:super_admin');
    Route::post('/upload-restore', [BackupController::class, 'uploadRestore'])->middleware('role:super_admin');
    Route::post('/scan-external', [BackupController::class, 'scanExternal'])->middleware('role:super_admin');
    Route::post('/restore-external', [BackupController::class, 'restoreExternal'])->middleware('role:super_admin');
});
