<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $environment = (string) ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? '');
        $database = (string) ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? '');

        if ($environment !== 'testing' || ! str_ends_with($database, '_test')) {
            throw new \RuntimeException(
                "Refusing to run PHPUnit outside an isolated *_test database (APP_ENV={$environment}, DB_DATABASE={$database})."
            );
        }

        // The local `.env` file (APP_ENV=local) is loaded the moment the app
        // boots and Dotenv writes APP_ENV into $_SERVER. Laravel's Env
        // repository reads $_SERVER before $_ENV, so the app booted as "local":
        // VerifyCsrfToken::runningUnitTests() returned false, CSRF verification
        // stayed on, and every POST/PUT/PATCH web test failed with HTTP 419.
        // Re-assert the environment phpunit.xml documents on the channel
        // Laravel actually reads. The *_test database guard above still applies.
        $_SERVER['APP_ENV'] = 'testing';
        putenv('APP_ENV=testing');

        parent::setUp();

        $this->withoutVite();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }
}
