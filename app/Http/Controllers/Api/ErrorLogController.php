<?php

namespace App\Http\Controllers\Api;

use App\Models\ErrorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ErrorLogController
{
    public function client(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'level' => ['nullable', 'string'],
            'message' => ['required', 'string'],
            'stack' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        ErrorLog::query()->create([
            'level' => $payload['level'] ?? 'error',
            'message' => $payload['message'],
            'stack' => $payload['stack'] ?? null,
            'source' => $payload['source'] ?? 'client',
            'metadata' => $payload['metadata'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(200, max(1, (int) $request->query('limit', 50)));

        $query = ErrorLog::query()
            ->when($request->query('level'), fn ($query) => $query->where('level', $request->query('level')))
            ->when($request->query('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->query('source'), fn ($query) => $query->where('source', $request->query('source')))
            ->when($request->query('search'), fn ($query) => $query->where('message', 'like', '%'.$request->query('search').'%'))
            ->when($request->query('startDate'), fn ($query) => $query->where('createdAt', '>=', $request->query('startDate')))
            ->when($request->query('endDate'), fn ($query) => $query->where('createdAt', '<=', $request->query('endDate')));

        $total = (clone $query)->count();
        $logs = $query
            ->orderByDesc('createdAt')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

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
            'byLevel' => ErrorLog::query()->where('createdAt', '>=', $since)->select('level', DB::raw('COUNT(*) as count'))->groupBy('level')->get(),
            'byStatus' => ErrorLog::query()->where('createdAt', '>=', $since)->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->get(),
            'topSources' => ErrorLog::query()->where('createdAt', '>=', $since)->select('source', DB::raw('COUNT(*) as count'))->groupBy('source')->orderByDesc('count')->limit(10)->get(),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', 'string'],
            'resolutionNotes' => ['nullable', 'string'],
        ]);

        $log = ErrorLog::query()->findOrFail($id);
        $log->update([
            'status' => $payload['status'],
            'resolutionNotes' => $payload['resolutionNotes'] ?? null,
            'resolvedAt' => $payload['status'] === 'resolved' ? now() : null,
        ]);

        return response()->json($log->fresh());
    }

    public function clearAll(): JsonResponse
    {
        $deleted = ErrorLog::query()->delete();

        return response()->json(['ok' => true, 'deleted' => $deleted]);
    }

    public function destroy(string $id): JsonResponse
    {
        ErrorLog::query()->whereKey($id)->delete();

        return response()->json(['ok' => true]);
    }
}
