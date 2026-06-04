<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

final class DateFilterBounds
{
    public static function cappedAtToday(?string $value, bool $endOfDay = false): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            $date = Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }

        $today = now();

        if ($date->gt($today)) {
            $date = $today;
        }

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }
}
