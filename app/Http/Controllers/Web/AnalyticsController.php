<?php

namespace App\Http\Controllers\Web;

use App\Models\SurveyResponse;
use App\Models\Ticket;
use App\Models\User;
use App\Services\PredictiveService;
use App\Services\SettingsService;
use App\Support\DashboardAnalyticsCache;
use App\Support\DashboardBadgeCache;
use App\Support\DateFilterBounds;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AnalyticsController
{
    public function __construct(
        private readonly PredictiveService $predictiveService,
        private readonly SettingsService $settingsService,
    ) {}

    public function reports(Request $request): View|JsonResponse
    {
        $payload = $this->reportsPayload($request);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($payload);
        }

        return view('dashboard.reports', $payload);
    }

    private function reportsPayload(Request $request): array
    {
        $user = $request->user();
        $query = $this->filteredResponsesQuery($request);

        $stats = Cache::remember(
            DashboardAnalyticsCache::key($user, 'reports_stats', $this->cacheFilters($request, [
                'department',
                'dateFilter',
                'startDate',
                'endDate',
            ])),
            60,
            fn () => $this->predictiveService->getStats(clone $query)
        );

        // Get comparison data from previous period for trend analysis
        $comparisonStats = Cache::remember(
            DashboardAnalyticsCache::key($user, 'reports_compare', $this->cacheFilters($request, [
                'department',
                'dateFilter',
                'startDate',
                'endDate',
            ])),
            60,
            fn () => $this->getComparisonStats($request, $user)
        );

        // Get monthly trend data for charts
        $trendData = Cache::remember(
            DashboardAnalyticsCache::key($user, 'reports_trend', $this->cacheFilters($request, [
                'department',
            ])),
            60,
            fn () => $this->getMonthlyTrend($request, $user)
        );

        // Get department trend data for radar/sparkline charts
        $deptTrends = Cache::remember(
            DashboardAnalyticsCache::key($user, 'reports_dept_trends', $this->cacheFilters($request, [
                'department',
                'dateFilter',
                'startDate',
                'endDate',
            ])),
            120,
            fn () => $this->getDepartmentTrends($request, $user)
        );

        $tickets = Cache::remember(
            DashboardAnalyticsCache::key($user, 'reports_tickets', $this->cacheFilters($request, ['department'])),
            60,
            fn () => $this->scopedTicketsQuery($user)
                ->when(
                    $user?->role === 'head_of_department' && $user?->department,
                    fn ($query) => $query->where('department', $user->department),
                    fn ($query) => $query->when(
                        $request->query('department') && $request->query('department') !== 'all',
                        fn ($ticketQuery) => $ticketQuery->where('department', $request->query('department'))
                    )
                )
                ->get()
        );

        $stats = $this->localizeStats($stats);
        $trendData = $this->localizeTrendData($trendData);
        $deptTrends = $this->localizeDepartmentTrends($deptTrends);
        $tickets = $this->localizeTickets($tickets);

        return [
            'stats' => $stats,
            'comparisonStats' => $comparisonStats,
            'trendData' => $trendData,
            'deptTrends' => $deptTrends,
            'tickets' => $tickets,
            'changes' => [
                'averageScore' => ($stats['averageScore'] ?? 0) - ($comparisonStats['averageScore'] ?? 0),
                'npsScore' => ($stats['npsScore'] ?? 0) - ($comparisonStats['npsScore'] ?? 0),
                'totalResponses' => ($stats['totalResponses'] ?? 0) - ($comparisonStats['totalResponses'] ?? 0),
            ],
        ];
    }

    private function localizeStats(array $stats): array
    {
        if (isset($stats['satisfactionDistribution'])) {
            $stats['satisfactionDistribution'] = collect($stats['satisfactionDistribution'])->map(function ($item) {
                $item['level'] = __($item['level'] === 'ممتاز' || $item['level'] === 'ظ…ظ…طھط§ط²' ? 'score_excellent' :
                    ($item['level'] === 'جيد' || $item['level'] === 'ط¬ظٹط¯' ? 'score_good' :
                    ($item['level'] === 'متوسط' || $item['level'] === 'ظ…طھظˆط³ط·' ? 'score_average' :
                    ($item['level'] === 'ضعيف' || $item['level'] === 'ط¶ط¹ظٹظپ' ? 'score_poor' : $item['level']))));

                return $item;
            })->all();
        }

        if (isset($stats['departmentScores'])) {
            $stats['departmentScores'] = collect($stats['departmentScores'])->map(function ($item) {
                $item['name'] = __($item['name']);

                return $item;
            })->all();
        }

        if (isset($stats['categoryScores'])) {
            $stats['categoryScores'] = collect($stats['categoryScores'])->map(function ($item) {
                $item['category'] = __($item['category']);

                return $item;
            })->all();
        }

        return $stats;
    }

    private function localizeTrendData(array $trendData): array
    {
        return collect($trendData)->map(function ($item) {
            if (isset($item['label'])) {
                $item['label'] = __($item['label']);
            }

            return $item;
        })->all();
    }

    private function localizeDepartmentTrends(array $deptTrends): array
    {
        return collect($deptTrends)->map(function ($item) {
            $item['name'] = __($item['name']);

            return $item;
        })->all();
    }

    private function localizeTickets($tickets)
    {
        return collect($tickets)->map(function ($item) {
            if (isset($item->department)) {
                $item->department = __($item->department);
            }

            return $item;
        });
    }

    private function getComparisonStats(Request $request, $user): array
    {
        // Determine the previous period based on current filter
        $dateFilter = $request->query('dateFilter', 'all');
        $now = now();

        $previousStart = null;
        $previousEnd = null;

        switch ($dateFilter) {
            case 'week':
                $previousStart = $now->copy()->subDays(14);
                $previousEnd = $now->copy()->subDays(8);
                break;
            case 'month':
                $previousStart = $now->copy()->subDays(60);
                $previousEnd = $now->copy()->subDays(31);
                break;
            case 'quarter':
                $previousStart = $now->copy()->subDays(180);
                $previousEnd = $now->copy()->subDays(91);
                break;
            case 'custom':
                if ($request->query('startDate') && $request->query('endDate')) {
                    $rangeDays = Carbon::parse($request->query('endDate'))->diffInDays(Carbon::parse($request->query('startDate')));
                    $previousEnd = Carbon::parse($request->query('startDate'))->subDay();
                    $previousStart = $previousEnd->copy()->subDays($rangeDays);
                }
                break;
            default:
                // For 'all', compare last 30 days with previous 30 days
                $previousStart = $now->copy()->subDays(60);
                $previousEnd = $now->copy()->subDays(31);
                break;
        }

        if (! $previousStart || ! $previousEnd) {
            return ['totalResponses' => 0, 'averageScore' => 0, 'npsScore' => 0];
        }

        $prevQuery = SurveyResponse::query()
            ->when($user?->tenantId, fn ($q) => $q->where('tenantId', $user->tenantId))
            ->when(
                $user?->role === 'head_of_department' && $user?->department,
                fn ($q) => $q->where('department', $user->department),
                fn ($q) => $q->when(
                    $request->query('department') && $request->query('department') !== 'all',
                    fn ($respQ) => $respQ->where('department', $request->query('department'))
                )
            )
            ->whereBetween('submittedAt', [$previousStart, $previousEnd]);

        return $this->predictiveService->getStats($prevQuery);
    }

    private function getMonthlyTrend(Request $request, $user): array
    {
        $months = 6; // Last 6 months
        $trend = [];

        $baseQuery = SurveyResponse::query()
            ->when($user?->tenantId, fn ($q) => $q->where('tenantId', $user->tenantId))
            ->when(
                $user?->role === 'head_of_department' && $user?->department,
                fn ($q) => $q->where('department', $user->department),
                fn ($q) => $q->when(
                    $request->query('department') && $request->query('department') !== 'all',
                    fn ($respQ) => $respQ->where('department', $request->query('department'))
                )
            );

        for ($i = $months - 1; $i >= 0; $i--) {
            $startOfMonth = now()->subMonths($i)->startOfMonth();
            $endOfMonth = now()->subMonths($i)->endOfMonth();

            $monthQuery = (clone $baseQuery)
                ->whereBetween('submittedAt', [$startOfMonth, $endOfMonth]);

            $monthStats = $this->predictiveService->getStats($monthQuery);

            $trend[] = [
                'month' => $startOfMonth->format('Y-m'),
                'label' => $startOfMonth->locale(app()->getLocale())->shortMonthName,
                'year' => $startOfMonth->year,
                'averageScore' => round($monthStats['averageScore'] ?? 0, 1),
                'npsScore' => round($monthStats['npsScore'] ?? 0, 1),
                'totalResponses' => $monthStats['totalResponses'] ?? 0,
                'excellent' => $monthStats['satisfactionDistribution'][0]['count'] ?? 0,
                'good' => $monthStats['satisfactionDistribution'][1]['count'] ?? 0,
                'average' => $monthStats['satisfactionDistribution'][2]['count'] ?? 0,
                'poor' => $monthStats['satisfactionDistribution'][3]['count'] ?? 0,
            ];
        }

        return $trend;
    }

    private function getDepartmentTrends(Request $request, $user): array
    {
        // Get department scores for current and previous period for side-by-side comparison
        $currentQuery = $this->filteredResponsesQuery($request);
        $currentStats = $this->predictiveService->getStats(clone $currentQuery);
        $currentDepts = collect($currentStats['departmentScores'] ?? []);

        // Get previous period (same as comparison but per-department)
        $prevQuery = SurveyResponse::query()
            ->when($user?->tenantId, fn ($q) => $q->where('tenantId', $user->tenantId))
            ->when(
                $user?->role === 'head_of_department' && $user?->department,
                fn ($q) => $q->where('department', $user->department)
            );

        // Apply same date filter but shifted back
        $dateFilter = $request->query('dateFilter', 'all');
        $now = now();
        $shiftDays = match ($dateFilter) {
            'week' => 7,
            'month' => 30,
            'quarter' => 90,
            'custom' => 30,
            default => 30,
        };

        // For custom date, compute exact shift
        if ($dateFilter === 'custom' && $request->query('startDate') && $request->query('endDate')) {
            $shiftDays = Carbon::parse($request->query('endDate'))->diffInDays(Carbon::parse($request->query('startDate')));
        }

        $prevDepts = collect();

        if ($shiftDays > 0) {
            $prevQueryClone = clone $prevQuery;
            // Reuse existing filter logic but shift dates
            $prevQueryClone->when($request->query('dateFilter') && $request->query('dateFilter') !== 'all', function ($q) use ($request, $shiftDays): void {
                if ($request->query('dateFilter') === 'week') {
                    $q->whereBetween('submittedAt', [now()->subDays(14), now()->subDays(8)]);
                } elseif ($request->query('dateFilter') === 'month') {
                    $q->whereBetween('submittedAt', [now()->subDays(60), now()->subDays(31)]);
                } elseif ($request->query('dateFilter') === 'quarter') {
                    $q->whereBetween('submittedAt', [now()->subDays(180), now()->subDays(91)]);
                } elseif ($request->query('dateFilter') === 'custom' && $request->query('startDate') && $request->query('endDate')) {
                    $start = Carbon::parse($request->query('startDate'))->subDays($shiftDays);
                    $end = Carbon::parse($request->query('startDate'))->subDay();
                    $q->whereBetween('submittedAt', [$start, $end]);
                }
            });
            $prevStats = $this->predictiveService->getStats($prevQueryClone);
            $prevDepts = collect($prevStats['departmentScores'] ?? []);
        }

        // Merge current and previous scores
        return $currentDepts->map(function ($current) use ($prevDepts) {
            $prev = $prevDepts->firstWhere('name', $current['name']);

            return [
                'name' => $current['name'],
                'currentScore' => $current['score'],
                'currentCount' => $current['count'],
                'previousScore' => $prev['score'] ?? 0,
                'previousCount' => $prev['count'] ?? 0,
                'change' => round($current['score'] - ($prev['score'] ?? 0), 1),
            ];
        })->values()->all();
    }

    public function predictive(Request $request): View
    {
        $user = $request->user();
        $query = SurveyResponse::query()
            ->when($user?->tenantId, fn ($query) => $query->where('tenantId', $user->tenantId))
            ->when(
                $user?->role === 'head_of_department' && $user?->department,
                fn ($query) => $query->where('department', $user->department)
            );

        $alertsData = Cache::remember(
            DashboardAnalyticsCache::key($user, 'predictive_page_alerts'),
            60,
            fn () => $this->predictiveService->getAlerts(clone $query)
        );
        $settings = $this->settingsService->getAll($user?->tenantId);
        $activatedPlans = $settings['activatedPredictivePlans'] ?? [];

        return view('dashboard.predictive', compact('alertsData', 'activatedPlans'));
    }

    public function togglePredictivePlan(Request $request): RedirectResponse
    {
        $request->validate([
            'department' => ['required', 'string', 'max:120'],
        ]);

        $user = $request->user();
        $department = $request->input('department');
        $settings = $this->settingsService->getAll($user?->tenantId);
        $current = $settings['activatedPredictivePlans'] ?? [];

        if (in_array($department, $current, true)) {
            $updated = array_values(array_filter($current, fn ($item) => $item !== $department));
        } else {
            $updated = array_merge($current, [$department]);
        }

        $this->settingsService->update([
            'activatedPredictivePlans' => $updated,
        ], $user);

        DashboardBadgeCache::forgetPredictive($request->user());

        $message = in_array($department, $current, true)
            ? __('predictive_plan_deactivated', ['department' => $department])
            : __('predictive_plan_activated', ['department' => $department]);

        return redirect()->back()->with('success', $message);
    }

    public function hallOfFame(Request $request): View
    {
        $query = $this->hallOfFameResponsesQuery($request);
        $stats = Cache::remember(
            DashboardAnalyticsCache::key($request->user(), 'hall_of_fame_stats', $this->cacheFilters($request, [
                'dateFilter',
                'startDate',
                'endDate',
            ])),
            60,
            fn () => $this->predictiveService->getStats($query)
        );
        $search = $request->query('q');

        $departmentScores = collect($stats['departmentScores'] ?? [])
            ->when($search, fn ($collection) => $collection->filter(fn ($department) => stripos($department['name'], $search) !== false))
            ->sortByDesc('score')
            ->values()
            ->all();

        return view('dashboard.hall-of-fame', compact('departmentScores'));
    }

    private function filteredResponsesQuery(Request $request): Builder
    {
        $user = $request->user();

        return SurveyResponse::query()
            ->when($user?->tenantId, fn ($query) => $query->where('tenantId', $user->tenantId))
            ->when(
                $user?->role === 'head_of_department' && $user?->department,
                fn ($query) => $query->where('department', $user->department),
                fn ($query) => $query->when(
                    $request->query('department') && $request->query('department') !== 'all',
                    fn ($responseQuery) => $responseQuery->where('department', $request->query('department'))
                )
            )
            ->when($request->query('dateFilter') && $request->query('dateFilter') !== 'all', function ($query) use ($request): void {
                if ($request->query('dateFilter') === 'week') {
                    $query->where('submittedAt', '>=', now()->subDays(7));
                } elseif ($request->query('dateFilter') === 'month') {
                    $query->where('submittedAt', '>=', now()->subDays(30));
                } elseif ($request->query('dateFilter') === 'quarter') {
                    $query->where('submittedAt', '>=', now()->subDays(90));
                } elseif ($request->query('dateFilter') === 'custom') {
                    if ($startDate = DateFilterBounds::cappedAtToday($request->query('startDate'))) {
                        $query->where('submittedAt', '>=', $startDate);
                    }
                    if ($endDate = DateFilterBounds::cappedAtToday($request->query('endDate'), true)) {
                        $query->where('submittedAt', '<=', $endDate);
                    }
                }
            });
    }

    private function hallOfFameResponsesQuery(Request $request): Builder
    {
        $user = $request->user();

        return SurveyResponse::query()
            ->when($user?->tenantId, fn ($query) => $query->where('tenantId', $user->tenantId))
            ->when($request->query('dateFilter') && $request->query('dateFilter') !== 'all', function ($query) use ($request): void {
                if ($request->query('dateFilter') === 'week') {
                    $query->where('submittedAt', '>=', now()->subDays(7));
                } elseif ($request->query('dateFilter') === 'month') {
                    $query->where('submittedAt', '>=', now()->subDays(30));
                } elseif ($request->query('dateFilter') === 'year') {
                    $query->where('submittedAt', '>=', now()->subDays(365));
                } elseif ($request->query('dateFilter') === 'custom') {
                    if ($startDate = DateFilterBounds::cappedAtToday($request->query('startDate'))) {
                        $query->where('submittedAt', '>=', $startDate);
                    }
                    if ($endDate = DateFilterBounds::cappedAtToday($request->query('endDate'), true)) {
                        $query->where('submittedAt', '<=', $endDate);
                    }
                }
            });
    }

    private function scopedTicketsQuery(?User $user): Builder
    {
        return Ticket::query()
            ->when($user?->tenantId, fn ($query) => $query->whereHas('response', fn ($response) => $response->where('tenantId', $user->tenantId)))
            ->when($user?->role === 'head_of_department' && $user?->department, fn ($query) => $query->where('department', $user->department));
    }

    private function cacheFilters(Request $request, array $keys): array
    {
        $filters = [];

        foreach ($keys as $key) {
            $value = $request->query($key);

            if ($value !== null && $value !== '') {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }
}
