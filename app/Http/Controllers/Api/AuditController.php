<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Support\ApiResponse;
use App\Support\AuditRequestContext;
use App\Support\HandlesApiQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditController
{
    use HandlesApiQueries;

    public function index(Request $request): JsonResponse
    {
        $user = auth("api")->user();
        $pagination = $this->getPagination($request);

        $query = AuditLog::query()
            ->with(["user:id,name,username,role"])
            ->when($user?->tenantId, fn ($query) => $query->whereHas("user", fn ($nested) => $nested->where("tenantId", $user->tenantId)))
            ->when($request->query("userId"), fn ($query) => $query->where("userId", $request->query("userId")))
            ->when($request->query("action"), fn ($query) => $query->where("action", $request->query("action")))
            ->when($request->query("search"), function ($query) use ($request) {
                $search = addcslashes($request->query("search"), "%_");
                $query->where(function ($nested) use ($search) {
                    $nested->where("details", "like", "%" . $search . "%")
                        ->orWhere("action", "like", "%" . $search . "%")
                        ->orWhereHas("user", function ($userQuery) use ($search) {
                            $userQuery->where("name", "like", "%" . $search . "%")
                                ->orWhere("username", "like", "%" . $search . "%");
                        });
                });
            })
            ->when($request->query("startDate"), fn ($query) => $query->where("timestamp", ">=", $request->query("startDate")))
            ->when($request->query("endDate"), fn ($query) => $query->where("timestamp", "<=", $request->query("endDate")));

        $total = (clone $query)->count();
        $logs = $query
            ->orderByDesc("timestamp")
            ->skip(($pagination["page"] - 1) * $pagination["limit"])
            ->take($pagination["limit"])
            ->get()
            ->map(fn (AuditLog $log) => $this->transformLog($log));

        return ApiResponse::paginated($logs, $total, $pagination["page"], $pagination["limit"]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = auth("api")->user();
        $days = max(1, (int) $request->query("days", 7));
        $since = now()->subDays($days);

        return response()->json([
            "actionStats" => AuditLog::query()
                ->when($user?->tenantId, fn ($query) => $query->whereHas("user", fn ($nested) => $nested->where("tenantId", $user->tenantId)))
                ->where("timestamp", ">=", $since)
                ->select("action", DB::raw("COUNT(*) as count"))
                ->groupBy("action")
                ->orderByDesc("count")
                ->get(),
            "trendData" => $this->buildTrendData($since, $days, $user?->tenantId),
            "topUsers" => AuditLog::query()
                ->where("timestamp", ">=", $since)
                ->join("users", "users.id", "=", "audit_logs.userId")
                ->when($user?->tenantId, fn ($query) => $query->where("users.tenantId", $user->tenantId))
                ->select("users.name", "users.username", DB::raw("COUNT(*) as count"))
                ->groupBy("users.id", "users.name", "users.username")
                ->orderByDesc("count")
                ->limit(10)
                ->get(),
        ]);
    }

    public function recordEvent(Request $request): JsonResponse
    {
        $payload = $request->validate([
            "action" => ["required", "string"],
            "messageKey" => ["nullable", "string"],
            "params" => ["nullable", "array"],
        ]);

        $user = auth("api")->user();
        if ($user) {
            AuditLog::query()->create([
                "userId" => $user->id,
                "action" => $payload["action"],
                "details" => json_encode($payload, JSON_UNESCAPED_UNICODE),
                "ipAddress" => AuditRequestContext::ipAddress($request),
                "userAgent" => AuditRequestContext::userAgent($request),
                "deviceName" => AuditRequestContext::deviceName($request),
            ]);
        }

        return ApiResponse::ok();
    }

    private function buildTrendData($since, int $days, ?string $tenantId): array
    {
        $counts = AuditLog::query()
            ->where("timestamp", ">=", $since)
            ->when($tenantId, fn ($query) => $query->whereHas("user", fn ($nested) => $nested->where("tenantId", $tenantId)))
            ->selectRaw("DATE(timestamp) as date, COUNT(*) as count")
            ->groupBy("date")
            ->pluck("count", "date");

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateKey = $date->format("Y-m-d");
            $label = $date->day . "/" . $date->month;
            $result[] = [
                "date" => $label,
                "count" => (int) ($counts[$dateKey] ?? 0),
            ];
        }

        return $result;
    }

    private function transformLog(AuditLog $log): array
    {
        return [
            "id" => $log->id,
            "userId" => $log->userId,
            "action" => $log->action,
            "details" => $log->details,
            "timestamp" => optional($log->timestamp)->toISOString(),
            "ipAddress" => $log->ipAddress,
            "userAgent" => $log->userAgent,
            "deviceName" => $log->deviceName,
            "user" => $log->user ? [
                "id" => $log->user->id,
                "name" => $log->user->name,
                "username" => $log->user->username,
                "role" => $log->user->role,
            ] : null,
        ];
    }
}
