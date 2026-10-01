<?php

namespace App\Licensing\Support;

/**
 * Normalised result of a call to the LicenseHub server.
 */
final class ServerResponse
{
    private function __construct(
        public readonly bool $ok,
        public readonly int $status,
        public readonly array $data,
        public readonly ?string $code,
        public readonly ?string $message,
        public readonly bool $transportError,
    ) {}

    public static function ok(int $status, array $data): self
    {
        return new self(true, $status, $data, null, null, false);
    }

    public static function failure(int $status, ?string $code = null, ?string $message = null): self
    {
        return new self(false, $status, [], $code, $message, false);
    }

    public static function transportError(?string $message = null): self
    {
        return new self(false, 0, [], 'network_error', $message, true);
    }
}
