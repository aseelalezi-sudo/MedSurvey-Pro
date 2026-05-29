<?php

namespace App\Http\Controllers\Api;

use App\Models\ErrorLog;
use App\Support\ApiResponse;
use App\Support\HandlesApiQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ErrorLogController
{
    use HandlesApiQueries;

    public function client(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'level' => ['nullable', 'string'],
            'message' => ['required', 'string', 'max:2000'],
            'stack' => ['nullable', 'string', 'max:20000'],
            'source' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ]);

        ErrorLog::query()->create([
            'level' => $payload['level'] ?? 'error',
            'message' => $payload['message'],
            'stack' => $payload['stack'] ?? null,
            'source' => $payload['source'] ?? 'client',
            'metadata' => $payload['metadata'] ?? null,
        ]);

        return ApiResponse::ok();
    }

    public function index(Request $request): JsonResponse
    {
        $pagination = $this->getPagination($request);

        $query = ErrorLog::query()
            ->when($request->query('level'), fn ($query) => $query->where('level', $request->query('level')))
            ->when($request->query('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->query('source'), fn ($query) => $query->where('source', $request->query('source')))
            ->when($request->query('search'), function ($query) use ($request) {
                $search = addcslashes($request->query('search'), '%_');
                $query->where('message', 'like', '%'.$search.'%');
            })
            ->when($request->query('startDate'), fn ($query) => $query->where('createdAt', '>=', $request->query('startDate')))
            ->when($request->query('endDate'), fn ($query) => $query->where('createdAt', '<=', $request->query('endDate')));

        $total = (clone $query)->count();
        $logs = $query
            ->orderByDesc('createdAt')
            ->skip(($pagination['page'] - 1) * $pagination['limit'])
            ->take($pagination['limit'])
            ->get();

        return ApiResponse::paginated($logs, $total, $pagination['page'], $pagination['limit']);
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
            'status' => ['required', 'string', 'in:new,investigating,resolved,ignored'],
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
        $user = auth('api')->user();

        // FIX: Added authorization check
        if (! in_array($user?->role, ['super_admin', 'admin'], true)) {
            return ApiResponse::error('Unauthorized', 403);
        }

        $deleted = ErrorLog::query()->delete();

        return ApiResponse::ok(['deleted' => $deleted]);
    }

    public function destroy(string $id): JsonResponse
    {
        ErrorLog::query()->whereKey($id)->delete();

        return ApiResponse::ok();
    }
}
