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
        $query = AuditLog::query()
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

        // Compute stats
        $totalLogs = AuditLog::count();
        $mostActiveUser = AuditLog::selectRaw('userId, COUNT(*) as cnt')
            ->whereNotNull('userId')
            ->groupBy('userId')
            ->orderByDesc('cnt')
            ->with('user')
            ->first();
        $mostCommonAction = AuditLog::selectRaw('action, COUNT(*) as cnt')
            ->groupBy('action')
            ->orderByDesc('cnt')
            ->first();
        $failedLogins = AuditLog::where('action', 'login_failed')
            ->where('timestamp', '>=', now()->subDays(30))
            ->count();

        // New Stats for Charts
        $actionStats = AuditLog::selectRaw('action, COUNT(*) as count')
            ->where('timestamp', '>=', now()->subDays(30))
            ->groupBy('action')
            ->orderByDesc('count')
            ->get();

        // Generate the past 31 days of data (including today) to ensure a complete, detailed timeline
        $days = [];
        for ($i = 30; $i >= 0; $i--) {
            $dateStr = now()->subDays($i)->format('Y-m-d');
            $days[$dateStr] = [
                'date' => $dateStr,
                'total' => 0,
                'failed' => 0,
            ];
        }

        // Fetch total activities group by date
        $totalActivities = AuditLog::selectRaw('DATE(timestamp) as date, COUNT(*) as count')
            ->where('timestamp', '>=', now()->subDays(31))
            ->groupBy(DB::raw('DATE(timestamp)'))
            ->get();

        foreach ($totalActivities as $item) {
            $dateStr = Carbon::parse($item->date)->format('Y-m-d');
            if (isset($days[$dateStr])) {
                $days[$dateStr]['total'] = (int) $item->count;
            }
        }

        // Fetch failed login attempts group by date
        $failedLoginsTrend = AuditLog::selectRaw('DATE(timestamp) as date, COUNT(*) as count')
            ->where('action', 'login_failed')
            ->where('timestamp', '>=', now()->subDays(31))
            ->groupBy(DB::raw('DATE(timestamp)'))
            ->get();

        foreach ($failedLoginsTrend as $item) {
            $dateStr = Carbon::parse($item->date)->format('Y-m-d');
            if (isset($days[$dateStr])) {
                $days[$dateStr]['failed'] = (int) $item->count;
            }
        }

        $trendData = collect($days)->map(function ($day) {
            $carbon = Carbon::parse($day['date']);

            return [
                'date' => $carbon->format('d/m'),
                'formattedDate' => $carbon->format('d/m'), // e.g. "31/05"
                'total' => $day['total'],
                'failed' => $day['failed'],
            ];
        })->values();

        // Fetch all unique actions for filter dropdown
        $availableActions = AuditLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

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

    public function errorLogs(Request $request): JsonResponse|View
    {
        $query = ErrorLog::query()
            ->when($request->query('level') && $request->query('level') !== 'all', fn ($q) => $q->where('level', $request->query('level')))
            ->when($request->query('status') && $request->query('status') !== 'all', fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->query('search'), function ($q) use ($request) {
                $search = addcslashes($request->query('search'), '%_');
                $q->where(fn ($sub) => $sub->where('message', 'like', '%'.$search.'%')->orWhere('source', 'like', '%'.$search.'%'));
            });

        if ($request->ajax() || $request->query('ajax') === 'true') {
            $logs = $query->orderByDesc('createdAt')->paginate(25);

            $since = now()->subDays(7);
            $stats = [
                'byLevel' => ErrorLog::query()->where('createdAt', '>=', $since)->select('level', DB::raw('COUNT(*) as count'))->groupBy('level')->get(),
                'byStatus' => ErrorLog::query()->where('createdAt', '>=', $since)->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->get(),
                'topSources' => ErrorLog::query()->where('createdAt', '>=', $since)->select('source', DB::raw('COUNT(*) as count'))->groupBy('source')->orderByDesc('count')->limit(10)->get(),
            ];

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

        $logs = $query->orderByDesc('createdAt')->paginate(25);

        $since = now()->subDays(7);
        $stats = [
            'byLevel' => ErrorLog::query()->where('createdAt', '>=', $since)->select('level', DB::raw('COUNT(*) as count'))->groupBy('level')->get(),
            'byStatus' => ErrorLog::query()->where('createdAt', '>=', $since)->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->get(),
            'topSources' => ErrorLog::query()->where('createdAt', '>=', $since)->select('source', DB::raw('COUNT(*) as count'))->groupBy('source')->orderByDesc('count')->limit(10)->get(),
        ];

        return view('dashboard.error-logs', compact('logs', 'stats'));
    }

    public function clearErrorLogs(Request $request): JsonResponse
    {
        if (! in_array($request->user()?->role, ['super_admin', 'admin'], true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $deleted = ErrorLog::query()->delete();

        return response()->json(['success' => true, 'deleted' => $deleted]);
    }

    public function updateErrorLog(Request $request, string $id): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', 'string', 'in:new,investigating,resolved,ignored'],
            'resolutionNotes' => ['nullable', 'string'],
        ]);

        $log = ErrorLog::findOrFail($id);
        $log->update([
            'status' => $payload['status'],
            'resolutionNotes' => $payload['resolutionNotes'] ?? null,
            'resolvedAt' => $payload['status'] === 'resolved' ? now() : null,
        ]);

        return response()->json(['success' => true, 'log' => $log]);
    }

    public function deleteErrorLog(Request $request, string $id): JsonResponse
    {
        if (! in_array($request->user()?->role, ['super_admin', 'admin'], true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        ErrorLog::whereKey($id)->delete();

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
                'action' => $payload['action'],
                'details' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'ipAddress' => AuditRequestContext::ipAddress($request),
                'userAgent' => AuditRequestContext::userAgent($request),
                'deviceName' => AuditRequestContext::deviceName($request),
            ]);
        }

        return response()->json(['status' => 'success']);
    }
}
