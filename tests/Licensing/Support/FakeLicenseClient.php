<?php

namespace Tests\Licensing\Support;

use App\Licensing\Services\LicenseClient;
use App\Licensing\Support\ServerResponse;

/**
 * An in-memory LicenseHub for tests. Signs tokens with the same JwtFactory
 * used by the unit tests, so the SDK verifies them like the real server's.
 */
final class FakeLicenseClient extends LicenseClient
{
    public const KID = 'test-kid';

    /** 'not_found' => device unknown (triggers activation), 'ok' => direct validate. */
    public string $validateResponse = 'not_found';

    /** null => heartbeat ok, 'forbidden' => revoked. */
    public ?string $heartbeatCode = null;

    public int $now = 0;

    public function __construct(
        private readonly string $secret,
        private readonly JwtFactory $factory,
        private readonly int $tokenTtlSeconds = 3600,
    ) {
        parent::__construct('http://licensehub.test', 'sdk-test', '1.0.0', 5);
    }

    public function keys(?string $ip = null, ?string $userAgent = null): ServerResponse
    {
        return ServerResponse::ok(200, [
            'keys' => [
                ['kid' => self::KID, 'alg' => 'EdDSA', 'public_key' => $this->factory->publicKeyB64(), 'status' => 'active'],
            ],
        ]);
    }

    public function activate(
        string $licenseKey,
        string $fingerprint,
        ?string $deviceName,
        ?string $platform,
        ?string $ip,
        ?string $userAgent,
    ): ServerResponse {
        return $this->issueToken($fingerprint);
    }

    public function validate(
        string $licenseKey,
        string $fingerprint,
        ?string $ip,
        ?string $userAgent,
    ): ServerResponse {
        if ($this->validateResponse === 'not_found') {
            return ServerResponse::failure(404, 'not_found');
        }

        return $this->issueToken($fingerprint);
    }

    public function heartbeat(
        string $licenseKey,
        string $fingerprint,
        ?string $ip,
        ?string $userAgent,
    ): ServerResponse {
        if ($this->heartbeatCode === 'forbidden') {
            return ServerResponse::failure(403, 'forbidden', 'License revoked.');
        }

        return ServerResponse::ok(200, [
            'valid' => true,
            'license' => ['status' => 'active'],
            'device' => ['uuid' => 'dev-test-uuid', 'status' => 'active'],
        ]);
    }

    public function deactivate(
        string $licenseKey,
        string $fingerprint,
        ?string $ip,
        ?string $userAgent,
    ): ServerResponse {
        return ServerResponse::ok(200, ['deactivated' => true]);
    }

    private function issueToken(string $fingerprint): ServerResponse
    {
        $devf = hash_hmac('sha256', $fingerprint, $this->secret);
        $now = $this->now === 0 ? time() : $this->now;

        $token = $this->factory->sign([
            'sub' => 'lic-test-uuid',
            'dev' => 'dev-test-uuid',
            'devf' => $devf,
            'prd' => 'medsurvey-pro',
            'cus' => 'cus-test-uuid',
            'typ' => 'perpetual',
            'fea' => ['advanced-reports', 'crm-sync'],
            'mxs' => 100,
            'iss' => 'licensehub',
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->tokenTtlSeconds,
            'jti' => 'test-jti',
        ], self::KID);

        return ServerResponse::ok(200, [
            'token' => $token,
            'expires_at' => gmdate('c', $now + $this->tokenTtlSeconds),
        ]);
    }
}
