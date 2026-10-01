<?php

namespace App\Licensing\Support;

use App\Licensing\Enums\LicenseStatus;

/**
 * The outcome of a licensing check, handed to the application layer. If the
 * check is valid, the decode claims / feature flags are made available.
 */
final class LicenseCheck
{
    private function __construct(
        public readonly LicenseStatus $status,
        public readonly string $reason,
        public readonly array $claims,
        public readonly ?string $token,
        public readonly ?int $code,
    ) {}

    public static function ok(array $claims, string $token): self
    {
        return new self(LicenseStatus::Valid, 'ok', $claims, $token, null);
    }

    public static function fail(LicenseStatus $status, string $reason, ?int $code = null, array $claims = []): self
    {
        return new self($status, $reason, $claims, null, $code);
    }

    public function valid(): bool
    {
        return $this->status === LicenseStatus::Valid;
    }

    public function features(): array
    {
        return (array) ($this->claims['fea'] ?? []);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features(), true);
    }

    public function type(): ?string
    {
        return isset($this->claims['typ']) ? (string) $this->claims['typ'] : null;
    }

    public function licenseUuid(): ?string
    {
        return isset($this->claims['sub']) ? (string) $this->claims['sub'] : null;
    }

    public function expiresAt(): ?string
    {
        $exp = $this->claims['exp'] ?? null;

        return is_int($exp) || (is_string($exp) && ctype_digit($exp)) ? date('c', (int) $exp) : null;
    }
}
