<?php

namespace Tests\Licensing;

use App\Licensing\Enums\LicenseStatus;
use App\Licensing\Models\LicenseState;
use App\Licensing\Services\LicenseClient;
use App\Licensing\Services\LicenseService;
use Tests\Licensing\Support\FakeLicenseClient;
use Tests\Licensing\Support\JwtFactory;

class LicenseServiceTest extends LicensingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('licensing.enabled', true);
        config()->set('licensing.product', 'medsurvey-pro');
        config()->set('licensing.issuer', 'licensehub');
        config()->set('licensing.server', 'http://licensehub.test');
    }

    private function bindFake(string $secret): FakeLicenseClient
    {
        $fake = new FakeLicenseClient($secret, JwtFactory::make());
        $this->app->instance(LicenseClient::class, $fake);

        return $fake;
    }

    private function makeToken(string $secret, JwtFactory $factory, string $fingerprint, int $exp, string $kid = FakeLicenseClient::KID): string
    {
        return $factory->sign([
            'sub' => 'lic-test-uuid',
            'dev' => 'dev-test-uuid',
            'devf' => hash_hmac('sha256', $fingerprint, $secret),
            'prd' => 'medsurvey-pro',
            'cus' => 'cus-test-uuid',
            'typ' => 'perpetual',
            'fea' => ['advanced-reports', 'crm-sync'],
            'mxs' => 100,
            'iss' => 'licensehub',
            'iat' => time(),
            'nbf' => time(),
            'exp' => $exp,
            'jti' => 'test-jti',
        ], $kid);
    }

    public function test_offline_boot_with_valid_cached_token_needs_no_network(): void
    {
        $secret = 'offline-hmac-secret';
        $factory = JwtFactory::make();
        $fp = 'stable-install-fingerprint';

        LicenseState::query()->create([
            'product' => 'medsurvey-pro',
            'status' => LicenseStatus::Valid,
            'license_key' => 'LHB-OFFLINE-KEY-0000-000',
            'fingerprint' => $fp,
            'token' => $this->makeToken($secret, $factory, $fp, time() + 3600, 'k1'),
            'public_keys' => ['k1' => $factory->publicKeyB64()],
        ]);

        config()->set('licensing.device_hash_secret', $secret);

        $check = app(LicenseService::class)->boot();

        $this->assertTrue($check->valid());
        $this->assertTrue($check->hasFeature('advanced-reports'));
        $this->assertSame('perpetual', $check->type());
        $this->assertSame('lic-test-uuid', $check->licenseUuid());
        $this->assertNotNull($check->expiresAt());
    }

    public function test_offline_boot_serves_cached_token_when_server_unreachable(): void
    {
        $secret = 'test-hmac-secret';
        $factory = JwtFactory::make();
        $fp = 'fp-1';

        LicenseState::query()->create([
            'product' => 'medsurvey-pro',
            'status' => LicenseStatus::Valid,
            'license_key' => 'LHB-OFFLINE-0000-0000-000',
            'fingerprint' => $fp,
            'token' => $this->makeToken($secret, $factory, $fp, time() + 3600, 'k1'),
            'public_keys' => ['k1' => $factory->publicKeyB64()],
        ]);

        config()->set('licensing.device_hash_secret', $secret);
        config()->set('licensing.server', 'http://127.0.0.1:9'); // unreachable

        $this->assertTrue(app(LicenseService::class)->boot()->valid());
    }

    public function test_fresh_installation_activates_online_and_persists_state(): void
    {
        $secret = 'test-hmac-secret-activate';

        config()->set('licensing.device_hash_secret', $secret);
        config()->set('licensing.key', 'LHB-ACTIVATE-KEY-0000-000');

        $this->bindFake($secret);

        $check = app(LicenseService::class)->boot();

        $this->assertTrue($check->valid());
        $this->assertTrue($check->hasFeature('crm-sync'));

        $state = LicenseState::query()->where('product', 'medsurvey-pro')->first();
        $this->assertNotNull($state);
        $this->assertSame(LicenseStatus::Valid, $state->status);
        $this->assertNotNull($state->token);
        $this->assertSame('LHB-ACTIVATE-KEY-0000-000', (string) $state->license_key);
    }

    public function test_no_license_key_returns_not_activated(): void
    {
        $secret = 'test-hmac-secret';
        $this->bindFake($secret);
        config()->set('licensing.device_hash_secret', $secret);

        $this->assertSame(LicenseStatus::NotActivated, app(LicenseService::class)->boot()->status);
    }

    public function test_expired_cached_token_is_refreshed_online_when_server_replies(): void
    {
        $secret = 'test-hmac-secret-refresh';
        $factory = JwtFactory::make();
        $fp = 'fp-refresh';

        LicenseState::query()->create([
            'product' => 'medsurvey-pro',
            'status' => LicenseStatus::Valid,
            'license_key' => 'LHB-REFRESH-0000-0000-000',
            'fingerprint' => $fp,
            'token' => $this->makeToken($secret, $factory, $fp, time() - 10, 'k-old'),
            'public_keys' => ['k-old' => $factory->publicKeyB64()],
        ]);

        config()->set('licensing.device_hash_secret', $secret);

        $fake = $this->bindFake($secret);
        $fake->validateResponse = 'ok';

        $check = app(LicenseService::class)->boot();

        $this->assertTrue($check->valid());
        $this->assertSame(LicenseStatus::Valid, LicenseState::query()->where('product', 'medsurvey-pro')->value('status'));
    }

    public function test_revoked_license_is_detected_by_heartbeat(): void
    {
        $secret = 'test-hmac-secret-revoked';
        $factory = JwtFactory::make();
        $fp = 'fp-revoked';

        LicenseState::query()->create([
            'product' => 'medsurvey-pro',
            'status' => LicenseStatus::Valid,
            'license_key' => 'LHB-REVOKED-0000-0000',
            'fingerprint' => $fp,
            'token' => $this->makeToken($secret, $factory, $fp, time() + 3600, 'k1'),
            'public_keys' => ['k1' => $factory->publicKeyB64()],
        ]);

        config()->set('licensing.device_hash_secret', $secret);

        $fake = $this->bindFake($secret);
        $fake->heartbeatCode = 'forbidden';

        $check = app(LicenseService::class)->heartbeat();

        $this->assertSame(LicenseStatus::Revoked, $check->status);
    }

    public function test_cached_token_bound_to_another_device_is_not_served_offline(): void
    {
        $secret = 'test-hmac-secret-devf';
        $factory = JwtFactory::make();
        $fp = 'real-install-fp';

        LicenseState::query()->create([
            'product' => 'medsurvey-pro',
            'status' => LicenseStatus::Valid,
            'license_key' => 'LHB-DEVF-0000-0000-000',
            'fingerprint' => $fp,
            // token is bound to a different fingerprint; must never be trusted
            'token' => $this->makeToken($secret, $factory, 'another-device', time() + 3600, 'k1'),
            'public_keys' => ['k1' => $factory->publicKeyB64()],
        ]);

        config()->set('licensing.device_hash_secret', $secret);
        config()->set('licensing.server', 'http://127.0.0.1:9'); // unreachable

        $check = app(LicenseService::class)->boot();

        $this->assertFalse($check->valid());
        $this->assertNotSame(LicenseStatus::Valid, $check->status);
    }
}
