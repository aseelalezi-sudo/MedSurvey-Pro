<?php

namespace App\Providers;

use App\Models\User;
use App\Services\SettingsService;
use App\View\Composers\DashboardLayoutComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
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
        TrustProxies::at(config('medsurvey.trusted_proxies') ?: '127.0.0.1,::1');

        Vite::createAssetPathsUsing(fn (string $path): string => '/'.$path);

        // Dashboard layout badge calculations (open tickets, predictive alerts)
        View::composer('layouts.dashboard', DashboardLayoutComposer::class);

        // Share settings globally with web views (cached per request)
        View::composer(['layouts.web', 'layouts.dashboard', 'pages.*', 'survey.*', 'auth.*'], function ($view) {
            if (array_key_exists('settings', $view->getData())) {
                return;
            }

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
            // Mobile collectors may sync a queue of offline responses at once,
            // and several devices can share one hospital NAT address.
            return Limit::perMinute(180)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            if ($this->app->environment('e2e')) {
                return Limit::none();
            }

            $username = (string) $request->input('username');

            return Limit::perMinute(5)->by($username.'|'.$request->ip());
        });

        // Super Admin gets all permissions implicitly
        Gate::before(function (User $user, $ability) {
            return $user->isSystemAdmin() ? true : null;
        });

        Gate::define('manage-super-admin-users', function (User $user) {
            return $user->isSystemAdmin();
        });
    }
}
