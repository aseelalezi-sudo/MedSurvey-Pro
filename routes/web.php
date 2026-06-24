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

Route::get('/survey-selection', [PublicSurveyController::class, 'selection'])->name('survey.selection');
Route::get('/survey/info', [PublicSurveyController::class, 'info'])->name('survey.info');
Route::get('/survey/take', [PublicSurveyController::class, 'take'])->name('survey.take');
Route::get('/survey/thanks', [PublicSurveyController::class, 'thanks'])->name('survey.thanks');
Route::post('/survey/responses', [PublicSurveyController::class, 'store'])->middleware('throttle:10,1')->name('survey.responses');

Route::middleware(['auth', 'audit.mutations'])->prefix('dashboard')->name('dashboard.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('index')->middleware('can:dashboard.view');
    Route::post('/change-password', [AccountController::class, 'changePassword'])->name('change-password');
    Route::get('/responses', [ResponseController::class, 'responses'])->name('responses')->middleware('can:responses.view');
    Route::get('/responses/filter', [ResponseController::class, 'filterResponses'])->name('responses.filter');
    Route::get('/responses/{id}/json', [ResponseController::class, 'showResponseJson'])->name('responses.json');

    Route::get('/kiosk/enter', [DashboardController::class, 'enterKioskMode'])->name('kiosk.enter');
    Route::get('/kiosk/exit', [DashboardController::class, 'exitKioskMode'])->name('kiosk.exit');

    Route::group([], function (): void {
        Route::get('/reports', [AnalyticsController::class, 'reports'])->name('reports')->middleware('can:reports.view');
        Route::get('/predictive', [AnalyticsController::class, 'predictive'])->name('predictive')->middleware('can:predictive.view');
        Route::post('/predictive/toggle', [AnalyticsController::class, 'togglePredictivePlan'])->name('predictive.toggle')->middleware('can:predictive.manage');
        Route::post('/audit/events', [OperationsController::class, 'recordEvent'])->name('audit.events')->middleware('can:operations.audit-logs.view');
        Route::get('/tickets', [TicketController::class, 'tickets'])->name('tickets')->middleware('can:tickets.view');
        Route::get('/tickets/filter', [TicketController::class, 'filterTickets'])->name('tickets.filter')->middleware('can:tickets.view');
        Route::patch('/tickets/{id}', [TicketController::class, 'updateTicket'])->name('tickets.update')->middleware('can:tickets.update');
        Route::delete('/tickets/{id}', [TicketController::class, 'destroyTicket'])->name('tickets.destroy')->middleware('can:tickets.delete');
        Route::get('/hall-of-fame', [AnalyticsController::class, 'hallOfFame'])->name('hall-of-fame')->middleware('can:hall-of-fame.view');
    });

    Route::group([], function (): void {
        Route::get('/surveys', [SurveyController::class, 'surveys'])->name('surveys')->middleware('can:surveys.view');
        Route::post('/surveys', [SurveyController::class, 'storeSurvey'])->name('surveys.store')->middleware('can:surveys.create');
        Route::post('/surveys/{id}/duplicate', [SurveyController::class, 'duplicateSurvey'])->name('surveys.duplicate')->middleware('can:surveys.duplicate');
        Route::put('/surveys/{id}', [SurveyController::class, 'updateSurvey'])->name('surveys.update')->middleware('can:surveys.update');
        Route::delete('/surveys/{id}', [SurveyController::class, 'destroySurvey'])->name('surveys.destroy')->middleware('can:surveys.delete');
        Route::patch('/surveys/{id}/toggle', [SurveyController::class, 'toggleSurvey'])->name('surveys.toggle')->middleware('can:surveys.toggle-status');

        Route::get('/users', [UserManagementController::class, 'users'])->name('users')->middleware('can:users.view');
        Route::post('/users', [UserManagementController::class, 'storeUser'])->name('users.store')->middleware('can:users.create');
        Route::put('/users/{id}', [UserManagementController::class, 'updateUser'])->name('users.update')->middleware('can:users.update');
        Route::patch('/users/{id}/toggle', [UserManagementController::class, 'toggleUser'])->name('users.toggle')->middleware('can:users.update');
        Route::delete('/users/{id}', [UserManagementController::class, 'destroyUser'])->name('users.destroy')->middleware('can:users.delete');

        Route::get('/settings', [SettingsController::class, 'settings'])->name('settings')->middleware('can:settings.view');
        Route::put('/settings', [SettingsController::class, 'updateSettings'])->name('settings.update')->middleware('can:settings.update');
        Route::post('/settings/usage-check', [SettingsController::class, 'usageCheck'])->name('settings.usage-check')->middleware('can:settings.view');

        Route::get('/audit', [OperationsController::class, 'audit'])->name('audit')->middleware('can:operations.audit-logs.view');
        Route::get('/monitoring', [OperationsController::class, 'monitoring'])->name('monitoring')->middleware('can:operations.monitoring.view');
        Route::get('/error-logs', [OperationsController::class, 'errorLogs'])->name('error-logs')->middleware('can:operations.error-logs.view');
        Route::post('/error-logs/clear', [OperationsController::class, 'clearErrorLogs'])->name('error-logs.clear')->middleware('can:operations.error-logs.delete');
        Route::post('/error-logs/{id}/update', [OperationsController::class, 'updateErrorLog'])->name('error-logs.update')->middleware('can:operations.error-logs.delete');
        Route::post('/error-logs/{id}/delete', [OperationsController::class, 'deleteErrorLog'])->name('error-logs.delete')->middleware('can:operations.error-logs.delete');

        Route::get('/backups', [BackupController::class, 'backups'])->name('backups')->middleware('can:operations.backups.view');
        Route::post('/backups', [BackupController::class, 'createBackup'])->name('backups.create')->middleware('can:operations.backups.create');
        Route::post('/backups/{filename}/verify', [BackupController::class, 'verifyBackup'])->name('backups.verify')->middleware('can:operations.backups.view');
        Route::get('/backups/{filename}/download', [BackupController::class, 'downloadBackup'])->name('backups.download')->middleware('can:operations.backups.download');
        Route::delete('/backups/{filename}', [BackupController::class, 'destroyBackup'])->name('backups.destroy')->middleware('can:operations.backups.delete');
    });
});

Route::post('/set-locale/{locale}', function (string $locale) {
    if (in_array($locale, ['ar', 'en'])) {
        session()->put('locale', $locale);
    }

    return redirect()->back();
})->name('set-locale');

Route::fallback(fn () => redirect()->route('home'));
