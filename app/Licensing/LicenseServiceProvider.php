<?php

namespace App\Licensing;

use App\Licensing\Commands\LicenseActivateCommand;
use App\Licensing\Commands\LicenseDeactivateCommand;
use App\Licensing\Commands\LicenseHeartbeatCommand;
use App\Licensing\Commands\LicenseRefreshKeysCommand;
use App\Licensing\Commands\LicenseStatusCommand;
use App\Licensing\Commands\LicenseValidateCommand;
use App\Licensing\Services\LicenseClient;
use App\Licensing\Services\LicenseService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the licensing SDK into the container and exposes its artisan commands.
 */
class LicenseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/licensing.php', 'licensing');

        $this->app->singleton(LicenseClient::class, function (): LicenseClient {
            return new LicenseClient(
                rtrim((string) config('licensing.server', ''), '/'),
                (string) config('licensing.client_name', 'sdk'),
                (string) config('licensing.client_version', '1.0.0'),
                (int) config('licensing.timeout', 5),
            );
        });

        $this->app->singleton(LicenseService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LicenseStatusCommand::class,
                LicenseActivateCommand::class,
                LicenseValidateCommand::class,
                LicenseHeartbeatCommand::class,
                LicenseDeactivateCommand::class,
                LicenseRefreshKeysCommand::class,
            ]);
        }
    }
}
