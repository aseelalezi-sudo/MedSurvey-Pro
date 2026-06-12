<?php

namespace App\Traits;

use App\Models\SurveyResponse;
use App\Models\User;
use App\Support\DateFilterBounds;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait FiltersResponses
{
    /**
     * Builds a common SurveyResponse query with tenant, role, department and date filters.
     */
    protected function buildBaseFilteredResponsesQuery(Request $request, ?User $user): Builder
    {
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
                if ($request->query('dateFilter') === 'today') {
                    $query->where('submittedAt', '>=', now()->startOfDay());
                } elseif ($request->query('dateFilter') === 'week') {
                    $query->where('submittedAt', '>=', now()->subDays(7));
                } elseif ($request->query('dateFilter') === 'month') {
                    $query->where('submittedAt', '>=', now()->subDays(30));
                } elseif ($request->query('dateFilter') === 'quarter') {
                    $query->where('submittedAt', '>=', now()->subDays(90));
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
            })
            // If there's no dateFilter but there's custom dates
            ->when(! $request->query('dateFilter') || $request->query('dateFilter') === 'all', function ($query) use ($request): void {
                if ($startDate = DateFilterBounds::cappedAtToday($request->query('startDate'))) {
                    $query->where('submittedAt', '>=', $startDate);
                }
                if ($endDate = DateFilterBounds::cappedAtToday($request->query('endDate'), true)) {
                    $query->where('submittedAt', '<=', $endDate);
                }
            });
    }
}
