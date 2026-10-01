<?php

namespace App\Licensing\Support;

use InvalidArgumentException;

/**
 * Minimal RFC 7519 JWT parser. No third-party dependency is pulled in:
 * we only need to split the three segments and read the claims for our
 * EdDSA tokens, so we keep it lean and side-channel-agnostic.
 *
 * @phpstan-type Payload array<string, mixed>
 */
class Token
{
    private function __construct(
        public readonly array $header,
        public readonly array $payload,
        public readonly string $signatureInput,
        public readonly string $signature,
    ) {}

    /**
     * @throws InvalidArgumentException when the token is malformed.
     */
    public static function parse(string $token): self
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Token must contain exactly three segments.');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        return new self(
            header: self::decodeJson($headerB64),
            payload: self::decodeJson($payloadB64),
            signatureInput: $headerB64.'.'.$payloadB64,
            signature: self::decodeBinary($signatureB64),
        );
    }

    public function kid(): ?string
    {
        return isset($this->header['kid']) ? (string) $this->header['kid'] : null;
    }

    public function algorithm(): ?string
    {
        return isset($this->header['alg']) ? (string) $this->header['alg'] : null;
    }

    public function claim(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    public function checksum(): string
    {
        return hash('sha256', $this->signatureInput);
    }

    /** @return array<string, mixed> */
    private static function decodeJson(string $base64Url): array
    {
        $decoded = json_decode(Base64Url::decode($base64Url), true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Token segment is not valid JSON.');
        }

        return $decoded;
    }

    private static function decodeBinary(string $base64Url): string
    {
        $decoded = Base64Url::decode($base64Url);

        if ($decoded === '') {
            throw new InvalidArgumentException('Token signature segment is empty.');
        }

        return $decoded;
    }
}
