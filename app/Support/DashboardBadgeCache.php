<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class DashboardBadgeCache
{
    public static function openTicketsKey(?User $user): string
    {
        $tenant = $user?->tenantId ?: 'global';

        return "dashboard_badges:open_tickets:tenant:{$tenant}";
    }

    public static function predictiveKey(?User $user): string
    {
        $userId = $user?->id ?: 'guest';
        $tenant = $user?->tenantId ?: 'global';

        return "dashboard_badges:predictive:user:{$userId}:tenant:{$tenant}";
    }

    public static function forgetFor(?User $user): void
    {
        Cache::forget(self::openTicketsKey($user));
        Cache::forget(self::predictiveKey($user));
    }

    public static function forgetOpenTickets(?User $user): void
    {
        Cache::forget(self::openTicketsKey($user));
    }

    public static function forgetPredictive(?User $user): void
    {
        Cache::forget(self::predictiveKey($user));
    }
}
