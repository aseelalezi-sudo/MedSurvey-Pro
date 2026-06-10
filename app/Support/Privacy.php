<?php

namespace App\Support;

class Privacy
{
    public static function maskPhone(?string $phone): string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $length = strlen($digits);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        $prefixLength = min(2, max(1, $length - 4));
        $suffixLength = min(2, $length - $prefixLength);
        $maskedLength = max(0, $length - $prefixLength - $suffixLength);

        return substr($digits, 0, $prefixLength)
            .str_repeat('*', $maskedLength)
            .substr($digits, -$suffixLength);
    }
}
