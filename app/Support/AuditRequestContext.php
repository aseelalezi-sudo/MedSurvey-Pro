<?php

namespace App\Support;

use Illuminate\Http\Request;

class AuditRequestContext
{
    public static function ipAddress(Request $request): ?string
    {
        return $request->ip();
    }

    public static function userAgent(Request $request): ?string
    {
        return $request->userAgent();
    }

    public static function deviceName(Request $request): string
    {
        $userAgent = $request->userAgent() ?? '';
        $browser = self::browser($userAgent);
        $platform = self::platform($userAgent);
        $deviceType = self::deviceType($userAgent);

        return trim("{$browser} on {$platform} - {$deviceType}");
    }

    private static function browser(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Edg/') => 'Microsoft Edge',
            str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'Chrome/') && ! str_contains($userAgent, 'Chromium') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') && str_contains($userAgent, 'Version/') => 'Safari',
            default => 'Unknown Browser',
        };
    }

    private static function platform(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Windows NT') => 'Windows',
            str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };
    }

    private static function deviceType(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'iPad') || str_contains($userAgent, 'Tablet') => 'Tablet',
            str_contains($userAgent, 'Mobile') || str_contains($userAgent, 'Android') || str_contains($userAgent, 'iPhone') => 'Mobile',
            default => 'Desktop',
        };
    }
}
