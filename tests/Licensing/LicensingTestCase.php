<?php

namespace Tests\Licensing;

use App\Licensing\Services\LicenseClient;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Self-contained base for licensing SDK tests. It boots the application but
 * swaps the default connection to an in-memory SQLite database (no MySQL
 * dependency) and creates only the licensing tables. Mult-tenant / spatie
 * seeding is intentionally skipped here.
 */
abstract class LicensingTestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        config()->set('cache.default', 'array');

        config()->set('database.default', 'licensing_memory');
        config()->set('database.connections.licensing_memory', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::purge('licensing_memory');
        DB::connection('licensing_memory')->reconnect();

        // Default: a real client wired to the configured server (unreachable in
        // most offline tests). Tests that need the server to succeed override
        // this with a fake via app()->instance().
        $this->app->bind(LicenseClient::class, fn (): LicenseClient => new LicenseClient(
            rtrim((string) config('licensing.server', ''), '/'),
            (string) config('licensing.client_name', 'sdk-test'),
            (string) config('licensing.client_version', '1.0.0'),
            (int) config('licensing.timeout', 1),
        ));

        $this->createLicenseSchema();
    }

    private function createLicenseSchema(): void
    {
        Schema::dropIfExists('license_states');

        Schema::create('license_states', function ($table): void {
            $table->string('id')->primary();
            $table->string('product')->unique();
            $table->string('status')->default('not_activated');
            $table->text('license_key')->nullable();
            $table->text('fingerprint')->nullable();
            $table->text('token')->nullable();
            $table->json('payload')->nullable();
            $table->json('public_keys')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index('status');
        });
    }
}
