<?php

use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\AnalyticsController;
use App\Http\Controllers\Web\AuthSessionController;
use App\Http\Controllers\Web\BackupController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\OperationsController;
use App\Http\Controllers\Web\PublicSurveyController;
use App\Http\Controllers\Web\ResponseController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\SurveyController;
use App\Http\Controllers\Web\TicketController;
use App\Http\Controllers\Web\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::post('/logout', [AuthSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/app/{any?}', function () {
    return view('app');
})->where('any', '.*')->name('legacy.app');

Route::get('/survey-selection', [PublicSurveyController::class, 'selection'])->name('survey.selection');
Route::get('/survey/info', [PublicSurveyController::class, 'info'])->name('survey.info');
Route::get('/survey/take', [PublicSurveyController::class, 'take'])->name('survey.take');
Route::get('/survey/thanks', [PublicSurveyController::class, 'thanks'])->name('survey.thanks');
Route::post('/survey/responses', [PublicSurveyController::class, 'store'])->middleware('throttle:10,1')->name('survey.responses');

Route::middleware(['auth', 'audit.mutations'])->prefix('dashboard')->name('dashboard.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('index');
    Route::post('/change-password', [AccountController::class, 'changePassword'])->name('change-password');
    Route::get('/responses', [ResponseController::class, 'responses'])->name('responses');
    Route::get('/responses/filter', [ResponseController::class, 'filterResponses'])->name('responses.filter');
    Route::get('/responses/{id}/json', [ResponseController::class, 'showResponseJson'])->name('responses.json');

    Route::get('/kiosk/enter', [DashboardController::class, 'enterKioskMode'])->name('kiosk.enter');
    Route::get('/kiosk/exit', [DashboardController::class, 'exitKioskMode'])->name('kiosk.exit');

    Route::middleware('web.role:super_admin,admin,unit_manager,head_of_department')->group(function (): void {
        Route::get('/reports', [AnalyticsController::class, 'reports'])->name('reports');
        Route::get('/predictive', [AnalyticsController::class, 'predictive'])->name('predictive');
        Route::post('/predictive/toggle', [AnalyticsController::class, 'togglePredictivePlan'])->name('predictive.toggle');
        Route::post('/audit/events', [OperationsController::class, 'recordEvent'])->name('audit.events');
        Route::get('/tickets', [TicketController::class, 'tickets'])->name('tickets');
        Route::get('/tickets/filter', [TicketController::class, 'filterTickets'])->name('tickets.filter');
        Route::patch('/tickets/{id}', [TicketController::class, 'updateTicket'])->name('tickets.update');
        Route::delete('/tickets/{id}', [TicketController::class, 'destroyTicket'])->middleware('web.role:super_admin,admin')->name('tickets.destroy');
        Route::get('/hall-of-fame', [AnalyticsController::class, 'hallOfFame'])->name('hall-of-fame');
    });

    Route::middleware('web.role:super_admin,admin')->group(function (): void {
        Route::get('/surveys', [SurveyController::class, 'surveys'])->name('surveys');
        Route::post('/surveys', [SurveyController::class, 'storeSurvey'])->name('surveys.store');
        Route::post('/surveys/{id}/duplicate', [SurveyController::class, 'duplicateSurvey'])->name('surveys.duplicate');
        Route::put('/surveys/{id}', [SurveyController::class, 'updateSurvey'])->name('surveys.update');
        Route::delete('/surveys/{id}', [SurveyController::class, 'destroySurvey'])->name('surveys.destroy');
        Route::patch('/surveys/{id}/toggle', [SurveyController::class, 'toggleSurvey'])->name('surveys.toggle');
        Route::get('/users', [UserManagementController::class, 'users'])->name('users');
        Route::post('/users', [UserManagementController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{id}', [UserManagementController::class, 'updateUser'])->name('users.update');
        Route::patch('/users/{id}/toggle', [UserManagementController::class, 'toggleUser'])->name('users.toggle');
        Route::delete('/users/{id}', [UserManagementController::class, 'destroyUser'])->name('users.destroy');
        Route::get('/settings', [SettingsController::class, 'settings'])->name('settings');
        Route::put('/settings', [SettingsController::class, 'updateSettings'])->name('settings.update');
        Route::post('/settings/usage-check', [SettingsController::class, 'usageCheck'])->name('settings.usage-check');
        Route::get('/audit', [OperationsController::class, 'audit'])->name('audit');
        Route::get('/monitoring', [OperationsController::class, 'monitoring'])->name('monitoring');
        Route::get('/error-logs', [OperationsController::class, 'errorLogs'])->name('error-logs');
        Route::post('/error-logs/clear', [OperationsController::class, 'clearErrorLogs'])->name('error-logs.clear');
        Route::post('/error-logs/{id}/update', [OperationsController::class, 'updateErrorLog'])->name('error-logs.update');
        Route::post('/error-logs/{id}/delete', [OperationsController::class, 'deleteErrorLog'])->name('error-logs.delete');
        Route::get('/backups', [BackupController::class, 'backups'])->name('backups');
        Route::post('/backups', [BackupController::class, 'createBackup'])->name('backups.create');
        Route::post('/backups/{filename}/verify', [BackupController::class, 'verifyBackup'])->name('backups.verify');
        Route::get('/backups/{filename}/download', [BackupController::class, 'downloadBackup'])->name('backups.download');
        Route::delete('/backups/{filename}', [BackupController::class, 'destroyBackup'])->name('backups.destroy');
        Route::post('/backups/upload', [BackupController::class, 'uploadBackup'])->name('backups.upload');
        Route::post('/backups/scan-external', [BackupController::class, 'scanExternalAjax'])->name('backups.scan-external');
        Route::post('/backups/verify-external', [BackupController::class, 'verifyExternalAjax'])->name('backups.verify-external');

        Route::middleware('web.role:super_admin')->group(function (): void {
            Route::post('/backups/{filename}/restore', [BackupController::class, 'restoreBackup'])->name('backups.restore');
            Route::post('/backups/upload-restore', [BackupController::class, 'uploadRestoreAjax'])->name('backups.upload-restore');
            Route::post('/backups/restore-external', [BackupController::class, 'restoreExternalAjax'])->name('backups.restore-external');
        });
    });
});

Route::post('/set-locale/{locale}', function (string $locale) {
    if (in_array($locale, ['ar', 'en'])) {
        session()->put('locale', $locale);
    }

    return redirect()->back();
})->name('set-locale');

// Backward-compatible GET for direct URL usage (links/bookmarks) — no state mutation
Route::get('/set-locale/{locale}', function (string $locale) {
    if (in_array($locale, ['ar', 'en'])) {
        session()->put('locale', $locale);
    }

    return redirect()->back();
})->name('set-locale.get');

Route::fallback(fn () => redirect()->route('home'));
