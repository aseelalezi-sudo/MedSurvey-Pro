<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditController
{
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(200, max(1, (int) $request->query('limit', 50)));

        $query = AuditLog::query()
            ->with(['user:id,name,username,role'])
            ->when($request->query('userId'), fn ($query) => $query->where('userId', $request->query('userId')))
            ->when($request->query('action'), fn ($query) => $query->where('action', $request->query('action')))
            ->when($request->query('search'), function ($query) use ($request) {
                $search = $request->query('search');
                $query->where(function ($nested) use ($search) {
                    $nested->where('details', 'like', '%'.$search.'%')
                        ->orWhere('action', 'like', '%'.$search.'%')
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', '%'.$search.'%')
                                ->orWhere('username', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($request->query('startDate'), fn ($query) => $query->where('timestamp', '>=', $request->query('startDate')))
            ->when($request->query('endDate'), fn ($query) => $query->where('timestamp', '<=', $request->query('endDate')));

        $total = (clone $query)->count();
        $logs = $query
            ->orderByDesc('timestamp')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->map(fn (AuditLog $log) => $this->transformLog($log));

        return response()->json([
            'data' => $logs,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $days = max(1, (int) $request->query('days', 7));
        $since = now()->subDays($days);

        return response()->json([
            'actionStats' => AuditLog::query()
                ->where('timestamp', '>=', $since)
                ->select('action', DB::raw('COUNT(*) as count'))
                ->groupBy('action')
                ->orderByDesc('count')
                ->get(),
            'trendData' => $this->buildTrendData($since, $days),
            'topUsers' => AuditLog::query()
                ->where('timestamp', '>=', $since)
                ->join('users', 'users.id', '=', 'audit_logs.userId')
                ->select('users.name', 'users.username', DB::raw('COUNT(*) as count'))
                ->groupBy('users.id', 'users.name', 'users.username')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
        ]);
    }

    public function recordEvent(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'action' => ['required', 'string'],
            'messageKey' => ['nullable', 'string'],
            'params' => ['nullable', 'array'],
        ]);

        $user = auth('api')->user();
        if ($user) {
            AuditLog::query()->create([
                'userId' => $user->id,
                'action' => $payload['action'],
                'details' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function buildTrendData($since, int $days): array
    {
        $counts = AuditLog::query()
            ->where('timestamp', '>=', $since)
            ->selectRaw('DATE(timestamp) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateKey = $date->format('Y-m-d');
            $label = $date->day.'/'.$date->month;
            $result[] = [
                'date' => $label,
                'count' => (int) ($counts[$dateKey] ?? 0),
            ];
        }

        return $result;
    }

    private function transformLog(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'userId' => $log->userId,
            'action' => $log->action,
            'details' => $log->details,
            'timestamp' => optional($log->timestamp)->toISOString(),
            'user' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
                'username' => $log->user->username,
                'role' => $log->user->role,
            ] : null,
        ];
    }
}
