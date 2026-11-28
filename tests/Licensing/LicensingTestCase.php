<?php

namespace Tests\Licensing;

use App\Licensing\Services\LicenseClient;
use Tests\TestCase;

abstract class LicensingTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');

        // Default: a real client wired to the configured server (unreachable in
        // most offline tests). Tests that need the server to succeed override
        // this with a fake via app()->instance().
        $this->app->bind(LicenseClient::class, fn (): LicenseClient => new LicenseClient(
            rtrim((string) config('licensing.server', ''), '/'),
            (string) config('licensing.client_name', 'sdk-test'),
            (string) config('licensing.client_version', '1.0.0'),
            (int) config('licensing.timeout', 1),
        ));

    }
}
