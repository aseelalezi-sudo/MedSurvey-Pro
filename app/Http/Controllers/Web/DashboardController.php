<?php

namespace App\Http\Controllers\Web;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\Ticket;
use App\Models\User;
use App\Services\PredictiveService;
use App\Support\DashboardAnalyticsCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController
{
    public function __construct(
        private readonly PredictiveService $predictiveService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $responsesQuery = SurveyResponse::query()
            ->when($user?->tenantId, fn ($query) => $query->where('tenantId', $user->tenantId))
            ->when(
                $user?->role === 'head_of_department' && $user?->department,
                fn ($query) => $query->where('department', $user->department)
            );

        $now = now();

        // Basic stats
        $stats = [
            'surveys' => Survey::query()
                ->when($user?->tenantId, fn ($query) => $query->where('tenantId', $user->tenantId))
                ->count(),
            'responses' => (clone $responsesQuery)->count(),
            'averageScore' => round((float) (clone $responsesQuery)->avg('overallScore'), 1),
            'openTickets' => $this->scopedTicketsQuery($user)->where('status', 'open')->count(),
        ];

        $advancedStats = Cache::remember(
            DashboardAnalyticsCache::key($user, 'advanced_stats'),
            60,
            fn () => $this->predictiveService->getStats(clone $responsesQuery)
        );
        $predictive = Cache::remember(
            DashboardAnalyticsCache::key($user, 'predictive_alerts'),
            60,
            fn () => $this->predictiveService->getAlerts(clone $responsesQuery)
        );
        $openTickets = $this->scopedTicketsQuery($user)
            ->where('status', 'open')
            ->orderByDesc('createdAt')
            ->limit(5)
            ->get();

        $nameResponsesCount = (clone $responsesQuery)->whereNotNull('patientName')->where('patientName', '<>', '')->count();
        $phoneResponsesCount = (clone $responsesQuery)->whereNotNull('patientPhone')->where('patientPhone', '<>', '')->count();
        $responseCount = max((int) $stats['responses'], 1);
        $identityStats = [
            'nameCount' => $nameResponsesCount,
            'nameRate' => (int) round(($nameResponsesCount / $responseCount) * 100),
            'phoneCount' => $phoneResponsesCount,
            'phoneRate' => (int) round(($phoneResponsesCount / $responseCount) * 100),
        ];

        $latestResponses = (clone $responsesQuery)
            ->with('survey')
            ->orderByDesc('submittedAt')
            ->limit(8)
            ->get();

        return view('dashboard.index', compact('stats', 'advancedStats', 'predictive', 'openTickets', 'identityStats', 'latestResponses'));
    }

    private function scopedTicketsQuery(?User $user): Builder
    {
        return Ticket::query()
            ->when($user?->tenantId, fn ($query) => $query->whereHas('response', fn ($response) => $response->where('tenantId', $user->tenantId)))
            ->when($user?->role === 'head_of_department' && $user?->department, fn ($query) => $query->where('department', $user->department));
    }
}
