<?php

namespace App\Licensing\Support;

/**
 * RFC 4648 base64url helpers with transparent padding toggling.
 */
final class Base64Url
{
    public static function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'), true)
            ?? throw new \InvalidArgumentException('Invalid base64url data.');
    }
}
