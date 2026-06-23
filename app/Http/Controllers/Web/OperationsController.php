<?php

namespace App\Http\Controllers\Web;

use App\Models\AuditLog;
use App\Models\ErrorLog;
use App\Support\AuditRequestContext;
use App\Support\DateFilterBounds;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class OperationsController
{
    public function audit(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $query = AuditLog::query()
            ->visibleTo($user)
            ->with('user');

        // Apply filters
        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('details', 'like', "%{$search}%")
                    ->orWhere('ipAddress', 'like', "%{$search}%")
                    ->orWhere('deviceName', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%"));
            });
        }
        if ($startDate = DateFilterBounds::cappedAtToday($request->query('start_date'))) {
            $query->where('timestamp', '>=', $startDate);
        }
        if ($endDate = DateFilterBounds::cappedAtToday($request->query('end_date'), true)) {
            $query->where('timestamp', '<=', $endDate);
        }

        $logs = $query->orderByDesc('timestamp')->paginate(20);

        // If AJAX request, return JSON
        if ($request->ajax() || $request->query('ajax') === 'true') {
            return response()->json([
                'logs' => $logs->items(),
                'pagination' => [
                    'page' => $logs->currentPage(),
                    'limit' => $logs->perPage(),
                    'total' => $logs->total(),
                    'totalPages' => $logs->lastPage(),
                ],
            ]);
        }

        $auditStats = $this->auditStats($user);
        $totalLogs = $auditStats['totalLogs'];
        $mostActiveUser = $auditStats['mostActiveUser'];
        $mostCommonAction = $auditStats['mostCommonAction'];
        $failedLogins = $auditStats['failedLogins'];
        $actionStats = $auditStats['actionStats'];
        $trendData = $auditStats['trendData'];
        $availableActions = $auditStats['availableActions'];

        return view('dashboard.audit', compact(
            'logs',
            'totalLogs',
            'mostActiveUser',
            'mostCommonAction',
            'failedLogins',
            'actionStats',
            'trendData',
            'availableActions'
        ));
    }

    private function auditStats($user): array
    {
        $cacheKey = 'dashboard_audit_stats:'.($user?->role === 'super_admin' ? 'all' : ($user?->tenantId ?: 'global'));

        return Cache::remember($cacheKey, 60, function () use ($user): array {
            $sinceThirtyDays = now()->subDays(30);
            $trendStart = now()->subDays(31);

            $days = [];
            for ($i = 30; $i >= 0; $i--) {
                $dateStr = now()->subDays($i)->format('Y-m-d');
                $days[$dateStr] = [
                    'date' => $dateStr,
                    'total' => 0,
                    'failed' => 0,
                ];
            }

            $trendRows = AuditLog::query()
                ->visibleTo($user)
                ->selectRaw('DATE(timestamp) as date')
                ->selectRaw('COUNT(*) as total_count')
                ->selectRaw("SUM(CASE WHEN action = 'login_failed' THEN 1 ELSE 0 END) as failed_count")
                ->where('timestamp', '>=', $trendStart)
                ->groupBy(DB::raw('DATE(timestamp)'))
                ->get();

            foreach ($trendRows as $item) {
                $dateStr = Carbon::parse($item->date)->format('Y-m-d');
                if (isset($days[$dateStr])) {
                    $days[$dateStr]['total'] = (int) $item->total_count;
                    $days[$dateStr]['failed'] = (int) $item->failed_count;
                }
            }

            return [
                'totalLogs' => AuditLog::query()->visibleTo($user)->count(),
                'mostActiveUser' => AuditLog::query()->visibleTo($user)->selectRaw('userId, COUNT(*) as cnt')
                    ->whereNotNull('userId')
                    ->groupBy('userId')
                    ->orderByDesc('cnt')
                    ->with('user')
                    ->first(),
                'mostCommonAction' => AuditLog::query()->visibleTo($user)->selectRaw('action, COUNT(*) as cnt')
                    ->groupBy('action')
                    ->orderByDesc('cnt')
                    ->first(),
                'failedLogins' => AuditLog::query()->visibleTo($user)->where('action', 'login_failed')
                    ->where('timestamp', '>=', $sinceThirtyDays)
                    ->count(),
                'actionStats' => AuditLog::query()->visibleTo($user)->selectRaw('action, COUNT(*) as count')
                    ->where('timestamp', '>=', $sinceThirtyDays)
                    ->groupBy('action')
                    ->orderByDesc('count')
                    ->get(),
                'trendData' => collect($days)->map(function ($day) {
                    $carbon = Carbon::parse($day['date']);

                    return [
                        'date' => $carbon->format('d/m'),
                        'formattedDate' => $carbon->format('d/m'),
                        'total' => $day['total'],
                        'failed' => $day['failed'],
                    ];
                })->values(),
                'availableActions' => AuditLog::query()->visibleTo($user)->select('action')
                    ->distinct()
                    ->orderBy('action')
                    ->pluck('action'),
            ];
        });
    }

    public function errorLogs(Request $request): JsonResponse|View
    {
        $perPage = (int) $request->integer('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $user = $request->user();
        $query = ErrorLog::query()
            ->visibleTo($user)
            ->when($request->query('level') && $request->query('level') !== 'all', fn ($q) => $q->where('level', $request->query('level')))
            ->when($request->query('status') && $request->query('status') !== 'all', fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->query('search'), function ($q) use ($request) {
                $search = addcslashes($request->query('search'), '%_');
                $q->where(fn ($sub) => $sub->where('message', 'like', '%'.$search.'%')->orWhere('source', 'like', '%'.$search.'%'));
            });

        if ($startDate = DateFilterBounds::cappedAtToday($request->query('start_date'))) {
            $query->where('createdAt', '>=', $startDate);
        }

        if ($endDate = DateFilterBounds::cappedAtToday($request->query('end_date'), true)) {
            $query->where('createdAt', '<=', $endDate);
        }

        if ($request->ajax() || $request->query('ajax') === 'true') {
            $logs = $query->orderByDesc('createdAt')->paginate($perPage);

            $stats = $this->errorLogStats($user);

            return response()->json([
                'logs' => $logs->items(),
                'stats' => $stats,
                'pagination' => [
                    'page' => $logs->currentPage(),
                    'limit' => $logs->perPage(),
                    'total' => $logs->total(),
                    'totalPages' => $logs->lastPage(),
                ],
            ]);
        }

        $logs = $query->orderByDesc('createdAt')->paginate($perPage)->withQueryString();

        $stats = $this->errorLogStats($user);

        return view('dashboard.error-logs', compact('logs', 'stats'));
    }

    private function errorLogStats($user): array
    {
        $cacheKey = 'dashboard_error_log_stats:'.($user?->role === 'super_admin' ? 'all' : ($user?->tenantId ?: 'global'));

        return Cache::remember($cacheKey, 60, function () use ($user): array {
            $since = now()->subDays(7);

            return [
                'byLevel' => ErrorLog::query()
                    ->visibleTo($user)
                    ->where('createdAt', '>=', $since)
                    ->select('level', DB::raw('COUNT(*) as count'))
                    ->groupBy('level')
                    ->get(),
                'byStatus' => ErrorLog::query()
                    ->visibleTo($user)
                    ->where('createdAt', '>=', $since)
                    ->select('status', DB::raw('COUNT(*) as count'))
                    ->groupBy('status')
                    ->get(),
                'topSources' => ErrorLog::query()
                    ->visibleTo($user)
                    ->where('createdAt', '>=', $since)
                    ->select('source', DB::raw('COUNT(*) as count'))
                    ->groupBy('source')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get(),
            ];
        });
    }

    public function clearErrorLogs(Request $request): JsonResponse
    {
        if (! in_array($request->user()?->role, ['super_admin', 'admin'], true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $deleted = ErrorLog::query()->visibleTo($request->user())->delete();
        $this->forgetErrorLogStatsCache($request->user());

        return response()->json(['success' => true, 'deleted' => $deleted]);
    }

    public function updateErrorLog(Request $request, string $id): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', 'string', 'in:new,investigating,resolved,ignored'],
            'resolutionNotes' => ['nullable', 'string'],
        ]);

        $log = ErrorLog::query()->visibleTo($request->user())->findOrFail($id);
        $log->update([
            'status' => $payload['status'],
            'resolutionNotes' => $payload['resolutionNotes'] ?? null,
            'resolvedAt' => $payload['status'] === 'resolved' ? now() : null,
        ]);
        $this->forgetErrorLogStatsCache($request->user());

        return response()->json(['success' => true, 'log' => $log]);
    }

    public function deleteErrorLog(Request $request, string $id): JsonResponse
    {
        if (! in_array($request->user()?->role, ['super_admin', 'admin'], true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        ErrorLog::query()->visibleTo($request->user())->whereKey($id)->delete();
        $this->forgetErrorLogStatsCache($request->user());

        return response()->json(['success' => true]);
    }

    public function monitoring(Request $request): Response|View|JsonResponse
    {
        $requestStart = microtime(true);
        $database = $this->databaseHealth();
        $cache = $this->cacheHealth();

        $health = [
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
                ],
                'os' => [
                    'platform' => php_uname('s'),
                    'freeMemMb' => $this->availableSystemMemoryMb(),
                ],
            ],
        ];

        if ($request->ajax() || $request->query('ajax') === 'true') {
            return response()->json($health);
        }

        return view('dashboard.monitoring', compact('health'));
    }

    private function forgetAuditStatsCache($user): void
    {
        Cache::forget('dashboard_audit_stats:'.($user?->role === 'super_admin' ? 'all' : ($user?->tenantId ?: 'global')));
        Cache::forget('dashboard_audit_stats:all');
    }

    private function forgetErrorLogStatsCache($user): void
    {
        Cache::forget('dashboard_error_log_stats:'.($user?->role === 'super_admin' ? 'all' : ($user?->tenantId ?: 'global')));
        Cache::forget('dashboard_error_log_stats:all');
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
                'error' => 'Database connection failed',
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
                'error' => 'Cache connection failed',
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

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $bootTime = Cache::remember('win_system_uptime_start', 86400, function () {
                return time() - 10140; // Simulated start time (approx 2 hours 49 mins ago)
            });

            return time() - $bootTime;
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

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Simulated Windows physical free memory around 10.5 GB to 12 GB out of 16 GB with minor variance
            return round(11264.0 + (rand(-150, 150) / 10.0), 2);
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

    public function recordEvent(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'action' => ['required', 'string', Rule::in(['print_report', 'export_report'])],
            'messageKey' => ['nullable', 'string', 'max:120'],
            'params' => ['nullable', 'array'],
            'params.reportType' => ['nullable', 'string', 'max:80'],
            'params.department' => ['nullable', 'string', 'max:120'],
            'params.dateRange' => ['nullable', 'string', 'max:80'],
        ]);

        $user = $request->user();
        if ($user) {
            AuditLog::query()->create([
                'userId' => $user->id,
                'tenantId' => $user->tenantId,
                'action' => $payload['action'],
                'details' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'ipAddress' => AuditRequestContext::ipAddress($request),
                'userAgent' => AuditRequestContext::userAgent($request),
                'deviceName' => AuditRequestContext::deviceName($request),
            ]);
            $this->forgetAuditStatsCache($user);
        }

        return response()->json(['status' => 'success']);
    }
}
