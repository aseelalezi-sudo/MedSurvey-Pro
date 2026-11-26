<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\DashboardAnalyticsCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardAnalyticsCacheTest extends TestCase
{
    public function test_response_invalidation_only_changes_facility_and_combined_reports(): void
    {
        $first = new User(['tenantId' => 'first']);
        $second = new User(['tenantId' => 'second']);
        $before = DashboardAnalyticsCache::key($first, 'stats');
        $other = DashboardAnalyticsCache::key($second, 'stats');
        $combined = DashboardAnalyticsCache::key(null, 'stats');
        DashboardAnalyticsCache::responseChanged('first');
        $this->assertNotSame($before, DashboardAnalyticsCache::key($first, 'stats'));
        $this->assertSame($other, DashboardAnalyticsCache::key($second, 'stats'));
        $this->assertNotSame($combined, DashboardAnalyticsCache::key(null, 'stats'));
    }

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
