<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class DashboardAnalyticsCache
{
    private const VERSION_KEY = 'dashboard_analytics:version';

    public static function key(?User $user, string $name, array $parts = []): string
    {
        $tenant = $user?->tenantId ?: 'global';
        $role = $user?->role ?: 'guest';
        $department = $user?->department ?: 'all';
        $version = self::version();
        $fingerprint = self::fingerprint($parts);

        return "dashboard_analytics:v{$version}:{$name}:tenant:{$tenant}:role:{$role}:department:{$department}{$fingerprint}";
    }

    public static function bump(): void
    {
        // increment() handles atomic add-if-not-exists internally
        if (! Cache::increment(self::VERSION_KEY)) {
            Cache::put(self::VERSION_KEY, 2);
        }
    }

    private static function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, fn () => 1);
    }

    private static function fingerprint(array $parts): string
    {
        if ($parts === []) {
            return '';
        }

        $parts = self::normalize($parts);
        $encoded = json_encode($parts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return ':filters:'.sha1($encoded ?: '');
    }

    private static function normalize(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::normalize($item);
            }
        }

        return $value;
    }
}
