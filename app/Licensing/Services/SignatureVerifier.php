<?php

namespace App\Licensing\Services;

use App\Licensing\Support\Token;
use InvalidArgumentException;

/**
 * Cryptographically verifies LicenseHub Ed25519 (EdDSA) tokens using the
 * sodium extension. Verification is deliberately double-checked:
 *   1. it must be the EdDSA algorithm,
 *   2. the signature must be valid over header.payload with the matching key,
 *   3. the public key length must be the canonical 32 bytes.
 */
class SignatureVerifier
{
    public function verify(Token $token, string $publicKeyB64): bool
    {
        if ($token->algorithm() !== 'EdDSA') {
            throw new InvalidArgumentException(sprintf('Unsupported token algorithm "%s".', $token->algorithm()));
        }

        try {
            $publicKey = base64_decode($publicKeyB64, true);
        } catch (\Throwable) {
            return false;
        }

        if (! is_string($publicKey) || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($token->signature, $token->signatureInput, $publicKey);
    }
}
