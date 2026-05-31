<?php

namespace App\Queries;

use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class ResponseFilterQuery
{
    public function __construct(
        private readonly Request $request,
        private readonly ?User $user,
    ) {}

    public static function make(Request $request, ?User $user): self
    {
        return new self($request, $user);
    }

    public function builder(): Builder
    {
        $request = $this->request;
        $user = $this->user;

        $query = SurveyResponse::query()
            ->when($user?->tenantId, fn ($q) => $q->where('tenantId', $user->tenantId))
            ->when(
                $user?->role === 'head_of_department' && $user?->department,
                fn ($q) => $q->where('department', $user->department)
            )
            ->when($request->query('department') && $request->query('department') !== 'all', fn ($q) => $q->where('department', $request->query('department')))
            ->when($request->query('score'), function ($q, $score) {
                if ($score === 'excellent') {
                    $q->where('overallScore', '>=', 85);
                } elseif ($score === 'good') {
                    $q->whereBetween('overallScore', [70, 84]);
                } elseif ($score === 'average') {
                    $q->whereBetween('overallScore', [50, 69]);
                } elseif ($score === 'poor') {
                    $q->where('overallScore', '<', 50);
                }
            })
            ->when($request->query('hasName') === '1', fn ($q) => $q->whereNotNull('patientName')->where('patientName', '<>', ''))
            ->when($request->query('hasPhone') === '1', fn ($q) => $q->whereNotNull('patientPhone')->where('patientPhone', '<>', ''))
            ->when($request->query('gender') && $request->query('gender') !== 'all', function ($q) use ($request) {
                $gender = strtolower(trim($request->query('gender')));
                $q->where('gender', 'like', "%{$gender}%");
            })
            ->when($request->query('dateFilter') && $request->query('dateFilter') !== 'all', function ($q) use ($request): void {
                if ($request->query('dateFilter') === 'today') {
                    $q->where('submittedAt', '>=', now()->startOfDay());
                } elseif ($request->query('dateFilter') === 'week') {
                    $q->where('submittedAt', '>=', now()->subDays(7));
                } elseif ($request->query('dateFilter') === 'month') {
                    $q->where('submittedAt', '>=', now()->subDays(30));
                } elseif ($request->query('dateFilter') === '3months') {
                    $q->where('submittedAt', '>=', now()->subMonths(3));
                } elseif ($request->query('dateFilter') === 'custom') {
                    if ($request->query('startDate')) {
                        $q->where('submittedAt', '>=', $request->query('startDate'));
                    }
                    if ($request->query('endDate')) {
                        $q->where('submittedAt', '<=', Carbon::parse($request->query('endDate'))->endOfDay());
                    }
                }
            })
            ->when($request->query('q'), function ($q, string $search): void {
                $q->where(function ($nested) use ($search): void {
                    $nested->where('patientName', 'like', "%{$search}%")
                        ->orWhere('patientPhone', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%")
                        ->orWhere('visitType', 'like', "%{$search}%")
                        ->orWhereHas('survey', fn ($surveyQuery) => $surveyQuery->where('title', 'like', "%{$search}%"));
                });
            });

        return $query;
    }

    public function sortColumn(): string
    {
        $sortByRaw = $this->request->query('sortBy', 'submittedAt-desc');
        $parts = explode('-', $sortByRaw);

        return $parts[0] === 'overallScore' ? 'overallScore' : 'submittedAt';
    }

    public function sortDirection(): string
    {
        $sortByRaw = $this->request->query('sortBy', 'submittedAt-desc');
        $parts = explode('-', $sortByRaw);

        return isset($parts[1]) && $parts[1] === 'asc' ? 'asc' : 'desc';
    }

    public function applySorting(Builder $query): Builder
    {
        return $query->orderBy($this->sortColumn(), $this->sortDirection());
    }
}
