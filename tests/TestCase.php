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

        parent::setUp();

        $this->withoutVite();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }
}
