<?php

namespace App\Support;

class NumberFormatter
{
    public static function format(float|int|null $value, int $decimals = 0): string
    {
        return number_format((float) ($value ?? 0), $decimals);
    }

    public static function compact(float|int|null $value): string
    {
        $value = (float) ($value ?? 0);
        $abs = abs($value);

        if ($abs >= 1000000) {
            return rtrim(rtrim(number_format($value / 1000000, $abs >= 10000000 ? 0 : 1), '0'), '.').'M';
        }

        if ($abs >= 1000) {
            return rtrim(rtrim(number_format($value / 1000, $abs >= 10000 ? 0 : 1), '0'), '.').'K';
        }

        return number_format($value, 0);
    }
}
