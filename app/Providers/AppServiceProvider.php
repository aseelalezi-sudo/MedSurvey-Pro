<?php

namespace App\Providers;

use App\Models\User;
use App\Services\SettingsService;
use App\View\Composers\DashboardLayoutComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        if ($this->app->environment('production') && ! $this->app->runningInConsole()) {
            $appUrl = (string) config('app.url');
            $productionHost = parse_url(str_contains($appUrl, '://') ? $appUrl : 'https://'.$appUrl, PHP_URL_HOST);
            $host = request()->getHost() ?: (string) request()->header('host');
            $isIp = (bool) filter_var($host, FILTER_VALIDATE_IP);

            if ($isIp) {
                // When accessing locally via IP (e.g., http://192.168.0.253),
                // private network IPs do not have SSL certificates, so keep URLs strictly HTTP
                // and ensure session cookies do not have Secure flag (otherwise browsers drop them).
                URL::forceScheme('http');
                config(['session.secure' => false]);
            } elseif ($host === 'medsurvey.almutawakelapps.com' || ($productionHost && $host === $productionHost) || request()->isSecure()) {
                // When accessing via the public domain or over secure proxy, force HTTPS
                // and ensure session cookies have the Secure flag for full transport security.
                URL::forceScheme('https');
                config(['session.secure' => true]);
            }
        }

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
            if ($this->app->environment('e2e')) {
                return Limit::none();
            }

            $username = (string) $request->input('username');

            return Limit::perMinute(5)->by($username.'|'.$request->ip());
        });

        // Super Admin gets all permissions implicitly
        Gate::before(function (User $user, $ability) {
            return $user->role === 'super_admin' ? true : null;
        });

        Gate::define('manage-super-admin-users', function (User $user) {
            return $user->role === 'super_admin';
        });
    }
}
