<?php

namespace App\Licensing\Services;

use Illuminate\Support\Str;

/**
 * Binds a signed token to the current installation.
 *
 * The raw fingerprint is the installation's identity. LicenseHub stores and
 * signs its HMAC-SHA256 digest (devf) using a shared secret, so the SDK can
 * confirm, fully offline, that a token was issued for this very installation
 * and was not replayed on another server.
 */
class DeviceIdentity
{
    public function hash(string $rawFingerprint, string $secret): string
    {
        return hash_hmac('sha256', $rawFingerprint, $secret);
    }

    public function matches(string $storedHash, string $rawFingerprint, string $secret): bool
    {
        return hash_equals($storedHash, $this->hash($rawFingerprint, $secret));
    }

    /**
     * Generate a fresh, unpredictable installation fingerprint. Persisted once
     * and reused so that revoking/seat-counting works across restarts.
     */
    public function generate(): string
    {
        return Str::uuid()->toString();
    }
}
