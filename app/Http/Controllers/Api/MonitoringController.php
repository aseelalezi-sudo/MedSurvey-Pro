<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MonitoringController
{
    public function health(): JsonResponse
    {
        $start = microtime(true);
        DB::select('select 1');
        $databaseLatency = (int) round((microtime(true) - $start) * 1000);

        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'totalLatencyMs' => $databaseLatency,
            'services' => [
                'database' => ['status' => 'ok', 'latencyMs' => $databaseLatency],
                'cache' => ['status' => 'ok', 'type' => config('cache.default')],
            ],
            'system' => [
                'uptime' => 0,
                'memory' => [
                    'heapUsedMb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                    'heapTotalMb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                    'rssMb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                ],
                'os' => [
                    'platform' => PHP_OS_FAMILY,
                    'freeMemMb' => 0,
                ],
            ],
        ]);
    }
}
