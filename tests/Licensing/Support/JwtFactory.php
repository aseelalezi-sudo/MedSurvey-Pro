<?php

namespace Tests\Licensing\Support;

use App\Licensing\Support\Base64Url;

/**
 * Builds EdDSA-signed JWT tokens exactly like LicenseHub (firebase/php-jwt
 * with alg=EdDSA), using raw sodium, so the SDK verifier is tested against
 * byte-identical tokens to those produced by the real server.
 */
final class JwtFactory
{
    private function __construct(
        private readonly string $secret,
        private readonly string $public,
    ) {}

    public static function make(): self
    {
        $keyPair = sodium_crypto_sign_keypair();

        return new self(
            sodium_crypto_sign_secretkey($keyPair),
            sodium_crypto_sign_publickey($keyPair),
        );
    }

    public function publicKeyB64(): string
    {
        return base64_encode($this->public);
    }

    public function sign(array $payload, string $kid): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'EdDSA', 'kid' => $kid];

        $signingInput = Base64Url::encode((string) json_encode($header))
            .'.'.Base64Url::encode((string) json_encode($payload));

        $signature = sodium_crypto_sign_detached($signingInput, $this->secret);

        return $signingInput.'.'.Base64Url::encode($signature);
    }
}
