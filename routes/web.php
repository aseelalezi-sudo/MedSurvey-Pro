<?php

use App\Http\Controllers\Web\AuthSessionController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\PublicSurveyController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthSessionController::class, 'store'])->name('login.store');
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
    Route::post('/change-password', [DashboardController::class, 'changePassword'])->name('change-password');
    Route::get('/responses', [DashboardController::class, 'responses'])->name('responses');
    Route::get('/responses/filter', [DashboardController::class, 'filterResponses'])->name('responses.filter');
    Route::get('/responses/{id}/json', [DashboardController::class, 'showResponseJson'])->name('responses.json');
    Route::get('/reports', [DashboardController::class, 'reports'])->name('reports');
    Route::get('/predictive', [DashboardController::class, 'predictive'])->name('predictive');
    Route::post('/predictive/toggle', [DashboardController::class, 'togglePredictivePlan'])->name('predictive.toggle');
    Route::post('/audit/events', [DashboardController::class, 'recordEvent'])->name('audit.events');
    Route::get('/tickets', [DashboardController::class, 'tickets'])->name('tickets');
    Route::get('/tickets/filter', [DashboardController::class, 'filterTickets'])->name('tickets.filter');
    Route::patch('/tickets/{id}', [DashboardController::class, 'updateTicket'])->name('tickets.update');
    Route::delete('/tickets/{id}', [DashboardController::class, 'destroyTicket'])->middleware('web.role:super_admin,admin')->name('tickets.destroy');
    Route::get('/hall-of-fame', [DashboardController::class, 'hallOfFame'])->name('hall-of-fame');

    Route::middleware('web.role:super_admin,admin')->group(function (): void {
        Route::get('/surveys', [DashboardController::class, 'surveys'])->name('surveys');
        Route::post('/surveys', [DashboardController::class, 'storeSurvey'])->name('surveys.store');
        Route::post('/surveys/{id}/duplicate', [DashboardController::class, 'duplicateSurvey'])->name('surveys.duplicate');
        Route::put('/surveys/{id}', [DashboardController::class, 'updateSurvey'])->name('surveys.update');
        Route::delete('/surveys/{id}', [DashboardController::class, 'destroySurvey'])->name('surveys.destroy');
        Route::patch('/surveys/{id}/toggle', [DashboardController::class, 'toggleSurvey'])->name('surveys.toggle');
        Route::get('/users', [DashboardController::class, 'users'])->name('users');
        Route::post('/users', [DashboardController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{id}', [DashboardController::class, 'updateUser'])->name('users.update');
        Route::patch('/users/{id}/toggle', [DashboardController::class, 'toggleUser'])->name('users.toggle');
        Route::delete('/users/{id}', [DashboardController::class, 'destroyUser'])->name('users.destroy');
        Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');
        Route::put('/settings', [DashboardController::class, 'updateSettings'])->name('settings.update');
        Route::post('/settings/usage-check', [DashboardController::class, 'usageCheck'])->name('settings.usage-check');
        Route::get('/audit', [DashboardController::class, 'audit'])->name('audit');
        Route::get('/monitoring', [DashboardController::class, 'monitoring'])->name('monitoring');
        Route::get('/error-logs', [DashboardController::class, 'errorLogs'])->name('error-logs');
        Route::post('/error-logs/clear', [DashboardController::class, 'clearErrorLogs'])->name('error-logs.clear');
        Route::post('/error-logs/{id}/update', [DashboardController::class, 'updateErrorLog'])->name('error-logs.update');
        Route::post('/error-logs/{id}/delete', [DashboardController::class, 'deleteErrorLog'])->name('error-logs.delete');
        Route::get('/backups', [DashboardController::class, 'backups'])->name('backups');
        Route::post('/backups', [DashboardController::class, 'createBackup'])->name('backups.create');
        Route::post('/backups/{filename}/verify', [DashboardController::class, 'verifyBackup'])->name('backups.verify');
        Route::get('/backups/{filename}/download', [DashboardController::class, 'downloadBackup'])->name('backups.download');
        Route::post('/backups/{filename}/restore', [DashboardController::class, 'restoreBackup'])->name('backups.restore');
        Route::delete('/backups/{filename}', [DashboardController::class, 'destroyBackup'])->name('backups.destroy');
        Route::post('/backups/upload', [DashboardController::class, 'uploadBackup'])->name('backups.upload');
        Route::post('/backups/upload-restore', [DashboardController::class, 'uploadRestoreAjax'])->name('backups.upload-restore');
        Route::post('/backups/scan-external', [DashboardController::class, 'scanExternalAjax'])->name('backups.scan-external');
        Route::post('/backups/verify-external', [DashboardController::class, 'verifyExternalAjax'])->name('backups.verify-external');
        Route::post('/backups/restore-external', [DashboardController::class, 'restoreExternalAjax'])->name('backups.restore-external');
    });
});

Route::get('/set-locale/{locale}', function (string $locale) {
    if (in_array($locale, ['ar', 'en'])) {
        session()->put('locale', $locale);
    }

    return redirect()->back();
})->name('set-locale');

Route::fallback(fn () => redirect()->route('home'));
