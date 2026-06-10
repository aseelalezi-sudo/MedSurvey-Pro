<?php

namespace App\Providers;

use App\Models\User;
use App\Services\SettingsService;
use App\View\Composers\DashboardLayoutComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::createAssetPathsUsing(fn (string $path): string => '/'.$path);

        // Dashboard layout badge calculations (open tickets, predictive alerts)
        View::composer('layouts.dashboard', DashboardLayoutComposer::class);

        // Share settings globally with web views (cached per request)
        View::composer(['layouts.web', 'layouts.dashboard', 'pages.*', 'survey.*', 'auth.*'], function ($view) {
            static $cachedSettings = [];
            $user = request()->user();
            $tenantId = $user?->tenantId ?? '__global__';

            if (! isset($cachedSettings[$tenantId])) {
                $settingsService = app(SettingsService::class);
                $cachedSettings[$tenantId] = $settingsService->getAll($user?->tenantId);
            }

            $view->with('settings', $cachedSettings[$tenantId]);
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            $username = (string) $request->input('username');

            return Limit::perMinute(5)->by($username.'|'.$request->ip());
        });

        // Role-based Gates
        Gate::define('manage-users', function (User $user) {
            return in_array($user->role, ['super_admin', 'admin'], true);
        });

        Gate::define('manage-surveys', function (User $user) {
            return in_array($user->role, ['super_admin', 'admin'], true);
        });

        Gate::define('view-all-reports', function (User $user) {
            return in_array($user->role, ['super_admin', 'admin', 'unit_manager'], true);
        });

        Gate::define('view-department-reports', function (User $user) {
            return in_array($user->role, ['super_admin', 'admin', 'unit_manager', 'head_of_department'], true);
        });

        Gate::define('view-responses', function (User $user) {
            return $user->role === 'staff';
        });

        Gate::define('export-data', function (User $user) {
            return in_array($user->role, ['super_admin', 'admin', 'unit_manager'], true);
        });

        Gate::define('delete-responses', function (User $user) {
            return $user->role === 'super_admin';
        });

        Gate::define('manage-backups-admin', function (User $user) {
            return in_array($user->role, ['super_admin', 'admin'], true);
        });

        Gate::define('manage-backups-super', function (User $user) {
            return $user->role === 'super_admin';
        });

        Gate::define('manage-error-logs-super', function (User $user) {
            return $user->role === 'super_admin';
        });

        Gate::define('manage-super-admin-users', function (User $user) {
            return $user->role === 'super_admin';
        });
    }
}
