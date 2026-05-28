<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class MonitoringController
{
    public function health(): JsonResponse
    {
        $requestStart = microtime(true);
        $database = $this->databaseHealth();
        $cache = $this->cacheHealth();

        return response()->json([
            'status' => $database['status'] === 'ok' && $cache['status'] === 'ok' ? 'ok' : 'degraded',
            'timestamp' => now()->toISOString(),
            'totalLatencyMs' => (int) round((microtime(true) - $requestStart) * 1000),
            'services' => [
                'database' => $database,
                'cache' => $cache,
            ],
            'system' => [
                'uptime' => $this->systemUptimeSeconds(),
                'memory' => [
                    'heapUsedMb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                    'heapTotalMb' => $this->phpMemoryLimitMb(),
                    'rssMb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                ],
                'os' => [
                    'platform' => PHP_OS_FAMILY,
                    'freeMemMb' => $this->availableSystemMemoryMb(),
                ],
            ],
        ]);
    }

    private function databaseHealth(): array
    {
        $start = microtime(true);

        try {
            DB::select('select 1');

            return [
                'status' => 'ok',
                'latencyMs' => (int) round((microtime(true) - $start) * 1000),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'error',
                'latencyMs' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function cacheHealth(): array
    {
        $key = 'monitoring_health_check';

        try {
            Cache::put($key, 'ok', now()->addMinute());
            $healthy = Cache::get($key) === 'ok';

            return [
                'status' => $healthy ? 'ok' : 'error',
                'type' => config('cache.default'),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'error',
                'type' => config('cache.default'),
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function systemUptimeSeconds(): ?int
    {
        if (is_readable('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            if (is_string($uptime) && preg_match('/^\d+(\.\d+)?/', $uptime, $matches)) {
                return (int) floor((float) $matches[0]);
            }
        }

        return null;
    }

    private function availableSystemMemoryMb(): ?float
    {
        if (is_readable('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            if (is_string($meminfo) && preg_match('/^MemAvailable:\s+(\d+)\s+kB/im', $meminfo, $matches)) {
                return round(((int) $matches[1]) / 1024, 2);
            }
        }

        return null;
    }

    private function phpMemoryLimitMb(): ?float
    {
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit === false || $memoryLimit === '-1') {
            return null;
        }

        if (! preg_match('/^(\d+)([KMG])?$/i', trim($memoryLimit), $matches)) {
            return null;
        }

        $value = (float) $matches[1];
        $unit = strtoupper($matches[2] ?? 'B');

        return match ($unit) {
            'K' => round($value / 1024, 2),
            'G' => round($value * 1024, 2),
            'M' => round($value, 2),
            default => round($value / 1024 / 1024, 2),
        };
    }
}
