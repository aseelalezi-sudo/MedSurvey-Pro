<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class DashboardAnalyticsCache
{
    private const VERSION_KEY = 'dashboard_analytics:version';

    public static function key(?User $user, string $name, array $parts = []): string
    {
        $tenant = $user?->tenantId ?: 'global';
        $role = $user?->role ?: 'guest';
        $department = $user?->department ?: 'all';
        $version = Cache::get(self::VERSION_KEY, '0').'.'.Cache::get(self::scopeKey($user?->tenantId), '0');
        $fingerprint = self::fingerprint($parts);

        return "dashboard_analytics:v{$version}:{$name}:tenant:{$tenant}:role:{$role}:department:{$department}{$fingerprint}";
    }

    public static function bump(): void
    {
        Cache::forever(self::VERSION_KEY, (string) Str::uuid());
    }

    public static function responseChanged(?string $tenantId): void
    {
        $version = (string) Str::uuid();
        Cache::forever(self::scopeKey(null), $version);
        if ($tenantId !== null) {
            Cache::forever(self::scopeKey($tenantId), $version);
        }
    }

    private static function scopeKey(?string $tenantId): string
    {
        return self::VERSION_KEY.($tenantId === null ? ':all' : ':tenant:'.$tenantId);
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
