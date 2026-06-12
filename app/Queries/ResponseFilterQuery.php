<?php

namespace App\Queries;

use App\Models\SurveyResponse;
use App\Models\User;
use App\Support\DateFilterBounds;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
            ->forUserAccess($user)
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
            ->when($request->query('gender') && $request->query('gender') !== 'all', function ($q) use ($request): void {
                $this->applyGenderFilter($q, (string) $request->query('gender'));
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
                    if ($startDate = DateFilterBounds::cappedAtToday($request->query('startDate'))) {
                        $q->where('submittedAt', '>=', $startDate);
                    }
                    if ($endDate = DateFilterBounds::cappedAtToday($request->query('endDate'), true)) {
                        $q->where('submittedAt', '<=', $endDate);
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

    private function applyGenderFilter(Builder $query, string $gender): void
    {
        $normalized = strtolower(trim($gender));

        if ($normalized === 'male') {
            $query->where(function (Builder $nested): void {
                $nested->whereRaw('LOWER(gender) = ?', ['male'])
                    ->orWhereIn('gender', ['ذكر']);
            });

            return;
        }

        if ($normalized === 'female') {
            $query->where(function (Builder $nested): void {
                $nested->whereRaw('LOWER(gender) = ?', ['female'])
                    ->orWhereIn('gender', ['أنثى', 'انثى']);
            });

            return;
        }

        $query->where('gender', $gender);
    }
}
