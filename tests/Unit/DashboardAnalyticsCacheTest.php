<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\DashboardAnalyticsCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardAnalyticsCacheTest extends TestCase
{
    public function test_cache_key_includes_stable_filter_fingerprint(): void
    {
        Cache::flush();

        $user = new User([
            'role' => 'admin',
            'tenantId' => 'tenant-a',
            'department' => 'Emergency',
        ]);

        $first = DashboardAnalyticsCache::key($user, 'reports_stats', [
            'department' => 'Emergency',
            'dateFilter' => 'month',
        ]);
        $sameFiltersDifferentOrder = DashboardAnalyticsCache::key($user, 'reports_stats', [
            'dateFilter' => 'month',
            'department' => 'Emergency',
        ]);
        $different = DashboardAnalyticsCache::key($user, 'reports_stats', [
            'department' => 'Pharmacy',
            'dateFilter' => 'month',
        ]);

        $this->assertSame($first, $sameFiltersDifferentOrder);
        $this->assertNotSame($first, $different);
    }
}
