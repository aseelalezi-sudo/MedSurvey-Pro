<?php

namespace App\Services;

use App\Models\SurveyAnswer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PredictiveService
{
    private const PREDICTIVE_LOOKBACK_DAYS = 30;

    private const PREDICTIVE_WINDOW_DAYS = 7;

    private const PREDICTIVE_MIN_DEPARTMENT_RESPONSES = 4;

    private const PREDICTIVE_MIN_WINDOW_RESPONSES = 2;

    private const PREDICTIVE_MIN_DROP_POINTS = 8;

    public function getAlerts(Builder $query): array
    {
        $now = now();
        $lookbackStart = $now->copy()->subDays(self::PREDICTIVE_LOOKBACK_DAYS);
        $currentWindowStart = $now->copy()->subDays(self::PREDICTIVE_WINDOW_DAYS);
        $previousWindowStart = $now->copy()->subDays(self::PREDICTIVE_WINDOW_DAYS * 2);

        $departmentRows = (clone $query)
            ->where('submittedAt', '>=', $lookbackStart)
            ->select('department')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('AVG(overallScore) as total_avg')
            ->selectRaw('MAX(submittedAt) as last_response_date')
            ->selectRaw('SUM(CASE WHEN submittedAt >= ? THEN 1 ELSE 0 END) as current_count', [$currentWindowStart])
            ->selectRaw('AVG(CASE WHEN submittedAt >= ? THEN overallScore ELSE NULL END) as current_avg', [$currentWindowStart])
            ->selectRaw('SUM(CASE WHEN submittedAt >= ? AND submittedAt < ? THEN 1 ELSE 0 END) as previous_count', [$previousWindowStart, $currentWindowStart])
            ->selectRaw('AVG(CASE WHEN submittedAt >= ? AND submittedAt < ? THEN overallScore ELSE NULL END) as previous_avg', [$previousWindowStart, $currentWindowStart])
            ->groupBy('department')
            ->get();

        $alertCandidateDepartments = $departmentRows
            ->filter(fn ($row) => (int) $row->total_count >= self::PREDICTIVE_MIN_DEPARTMENT_RESPONSES)
            ->values();

        $primaryDepartments = $alertCandidateDepartments
            ->filter(fn ($row) => (int) $row->current_count >= self::PREDICTIVE_MIN_WINDOW_RESPONSES && (int) $row->previous_count >= self::PREDICTIVE_MIN_WINDOW_RESPONSES)
            ->pluck('department')
            ->map(fn ($department) => (string) $department)
            ->values()
            ->all();

        $fallbackDepartments = $alertCandidateDepartments
            ->reject(fn ($row) => (int) $row->current_count >= self::PREDICTIVE_MIN_WINDOW_RESPONSES && (int) $row->previous_count >= self::PREDICTIVE_MIN_WINDOW_RESPONSES)
            ->pluck('department')
            ->map(fn ($department) => (string) $department)
            ->values()
            ->all();

        $currentResponseIdsByDepartment = $this->currentResponseIdsByDepartment(clone $query, $primaryDepartments, $currentWindowStart);
        $fallbackResponsesByDepartment = $this->fallbackResponsesByDepartment(clone $query, $fallbackDepartments, $lookbackStart);

        $alerts = $departmentRows
            ->map(function ($row) use ($currentResponseIdsByDepartment, $fallbackResponsesByDepartment, $previousWindowStart, $currentWindowStart) {
                $totalCount = (int) $row->total_count;
                if ($totalCount < self::PREDICTIVE_MIN_DEPARTMENT_RESPONSES) {
                    return null;
                }

                $currentCount = (int) $row->current_count;
                $previousCount = (int) $row->previous_count;
                $department = (string) $row->department;

                if ($currentCount >= self::PREDICTIVE_MIN_WINDOW_RESPONSES && $previousCount >= self::PREDICTIVE_MIN_WINDOW_RESPONSES) {
                    $previousAvg = (int) round((float) $row->previous_avg);
                    $currentAvg = (int) round((float) $row->current_avg);

                    return $this->buildAlert(
                        $department,
                        $previousAvg,
                        $currentAvg,
                        $currentResponseIdsByDepartment->get($department, []),
                        $currentCount + $previousCount,
                        $row->last_response_date,
                    );
                }

                return $this->fallbackAlertFromDepartmentResponses(
                    $fallbackResponsesByDepartment->get($department, collect()),
                    $department,
                    $previousWindowStart,
                    $currentWindowStart,
                );
            })
            ->filter()
            ->sortByDesc('drop')
            ->values();

        $totalResponsesAnalyzed = (int) $departmentRows->sum('total_count');

        return [
            'alerts' => $alerts,
            'stats' => [
                'totalDepts' => $departmentRows->count(),
                'activeWarnings' => $alerts->count(),
                'healthIndex' => $totalResponsesAnalyzed > 0
                    ? (int) round($departmentRows->sum(fn ($row) => ((float) $row->total_avg) * ((int) $row->total_count)) / $totalResponsesAnalyzed)
                    : 100,
                'totalResponsesAnalyzed' => $totalResponsesAnalyzed,
            ],
        ];
    }

    private function currentResponseIdsByDepartment(Builder $query, array $departments, mixed $currentWindowStart): Collection
    {
        if ($departments === []) {
            return collect();
        }

        return $query
            ->whereIn('department', $departments)
            ->where('submittedAt', '>=', $currentWindowStart)
            ->get(['id', 'department'])
            ->groupBy(fn ($response) => (string) $response->department)
            ->map(fn ($responses) => $responses->pluck('id')->all());
    }

    private function fallbackResponsesByDepartment(Builder $query, array $departments, mixed $lookbackStart): Collection
    {
        if ($departments === []) {
            return collect();
        }

        return $query
            ->whereIn('department', $departments)
            ->where('submittedAt', '>=', $lookbackStart)
            ->orderBy('department')
            ->orderBy('submittedAt')
            ->get(['id', 'department', 'overallScore', 'submittedAt'])
            ->groupBy(fn ($response) => (string) $response->department);
    }

    private function fallbackAlertFromDepartmentResponses(Collection $departmentResponses, string $department, mixed $previousWindowStart, mixed $currentWindowStart): ?array
    {
        $currentResponses = $departmentResponses->filter(fn ($response) => $response->submittedAt >= $currentWindowStart);
        $previousResponses = $departmentResponses->filter(fn ($response) => $response->submittedAt >= $previousWindowStart && $response->submittedAt < $currentWindowStart);

        if ($currentResponses->count() < self::PREDICTIVE_MIN_WINDOW_RESPONSES || $previousResponses->count() < self::PREDICTIVE_MIN_WINDOW_RESPONSES) {
            $chunks = $departmentResponses->values()->split(2);
            $previousResponses = $chunks->get(0, collect());
            $currentResponses = $chunks->get(1, collect());
        }

        if ($currentResponses->isEmpty() || $previousResponses->isEmpty()) {
            return null;
        }

        return $this->buildAlert(
            $department,
            (int) round($previousResponses->avg('overallScore')),
            (int) round($currentResponses->avg('overallScore')),
            $currentResponses->pluck('id')->all(),
            $currentResponses->count() + $previousResponses->count(),
            optional($departmentResponses->sortByDesc('submittedAt')->first()?->submittedAt)->toISOString(),
        );
    }

    private function buildAlert(string $department, int $previousAvg, int $currentAvg, array $currentResponseIds, int $sampleCount, mixed $lastResponseDate): ?array
    {
        $drop = $previousAvg - $currentAvg;
        if ($drop < self::PREDICTIVE_MIN_DROP_POINTS) {
            return null;
        }

        return [
            'id' => 'predictive-'.substr(sha1($department.'|'.$previousAvg.'|'.$currentAvg), 0, 12),
            'department' => $department,
            'previousAvg' => $previousAvg,
            'currentAvg' => $currentAvg,
            'predictedScore' => max(0, (int) round($currentAvg - max(3, $drop * 0.5))),
            'drop' => $drop,
            'dropPercentage' => (int) round(($drop / max($previousAvg, 1)) * 100),
            'keyDriver' => $this->predictiveKeyDriver($currentResponseIds),
            'sampleCount' => $sampleCount,
            'lastResponseDate' => is_string($lastResponseDate) ? $lastResponseDate : optional($lastResponseDate)->toISOString(),
        ];
    }

    // ─── Stats ───

    public function getStats(Builder $query): array
    {
        $now = now();
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $sixtyDaysAgo = $now->copy()->subDays(60);

        $aggregateRow = (clone $query)
            ->selectRaw('COUNT(*) as total_responses')
            ->selectRaw('AVG(overallScore) as average_score')
            ->selectRaw('AVG(CASE WHEN submittedAt < ? THEN overallScore ELSE NULL END) as previous_average_score', [$thirtyDaysAgo])
            ->selectRaw('SUM(CASE WHEN overallScore >= 85 THEN 1 ELSE 0 END) as excellent_count')
            ->selectRaw('SUM(CASE WHEN overallScore BETWEEN 70 AND 84 THEN 1 ELSE 0 END) as good_count')
            ->selectRaw('SUM(CASE WHEN overallScore BETWEEN 50 AND 69 THEN 1 ELSE 0 END) as average_count')
            ->selectRaw('SUM(CASE WHEN overallScore < 50 THEN 1 ELSE 0 END) as poor_count')
            ->selectRaw('SUM(CASE WHEN submittedAt >= ? THEN 1 ELSE 0 END) as current_volume', [$thirtyDaysAgo])
            ->selectRaw('SUM(CASE WHEN submittedAt >= ? AND submittedAt < ? THEN 1 ELSE 0 END) as previous_volume', [$sixtyDaysAgo, $thirtyDaysAgo])
            ->first();

        $totalResponses = (int) ($aggregateRow->total_responses ?? 0);
        $averageScore = (int) round($aggregateRow->average_score ?? 0);
        $previousAverageScore = (int) round($aggregateRow->previous_average_score ?? $averageScore);

        $previousQuery = clone $query;
        $previousQuery->where('submittedAt', '<', $thirtyDaysAgo);
        $previousNpsScore = $this->calculateNpsFromSubQuery((clone $previousQuery)->select('id'));

        $departmentScores = (clone $query)
            ->select('department', DB::raw('AVG(overallScore) as score'), DB::raw('COUNT(*) as count'))
            ->groupBy('department')
            ->orderByDesc('score')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->department,
                'score' => (int) round($row->score ?? 0),
                'count' => (int) $row->count,
            ]);

        $distribution = [
            ['level' => 'score_excellent', 'count' => (int) ($aggregateRow->excellent_count ?? 0), 'color' => '#10B981'],
            ['level' => 'score_good', 'count' => (int) ($aggregateRow->good_count ?? 0), 'color' => '#3B82F6'],
            ['level' => 'score_average', 'count' => (int) ($aggregateRow->average_count ?? 0), 'color' => '#F59E0B'],
            ['level' => 'score_poor', 'count' => (int) ($aggregateRow->poor_count ?? 0), 'color' => '#EF4444'],
        ];

        $responseIdSubQuery = (clone $query)->select('id');

        $currentVolume = (int) ($aggregateRow->current_volume ?? 0);
        $previousVolume = (int) ($aggregateRow->previous_volume ?? 0);

        $responseRate = 100;
        if ($previousVolume > 0) {
            $responseRate = (int) round(($currentVolume / $previousVolume) * 100);
        } elseif ($currentVolume > 0) {
            $responseRate = 100;
        } else {
            $responseRate = 0;
        }

        return [
            'totalResponses' => $totalResponses,
            'averageScore' => $averageScore,
            'previousAverageScore' => $previousAverageScore,
            'npsScore' => $this->calculateNpsFromSubQuery($responseIdSubQuery),
            'previousNpsScore' => $previousNpsScore ?: 0,
            'departmentScores' => $departmentScores,
            'hourlyStats' => $this->hourlyStats(clone $query),
            'dayStats' => $this->dayStats(clone $query),
            'categoryScores' => $this->categoryScoresFromSubQuery($responseIdSubQuery),
            'trendData' => $this->trendData(clone $query),
            'satisfactionDistribution' => $distribution,
            'responseRate' => $responseRate,
            'previousResponseRate' => 100, // Keep at 100 so the trend is `responseRate - 100`
        ];
    }

    public function hourlyStats(Builder $query): array
    {
        $rows = $query
            ->selectRaw('HOUR(submittedAt) as hour_number, AVG(overallScore) as score, COUNT(*) as count')
            ->groupBy('hour_number')
            ->get()
            ->keyBy('hour_number');

        return collect(range(0, 23))
            ->map(fn ($hour) => [
                'hour' => "{$hour}:00",
                'score' => (int) round($rows[$hour]->score ?? 0),
                'count' => (int) ($rows[$hour]->count ?? 0),
            ])
            ->all();
    }

    public function dayStats(Builder $query): array
    {
        $rows = $query
            ->selectRaw('DAYOFWEEK(submittedAt) as day_number, AVG(overallScore) as score, COUNT(*) as count')
            ->groupBy('day_number')
            ->get()
            ->keyBy('day_number');

        return collect([__('day_sunday'), __('day_monday'), __('day_tuesday'), __('day_wednesday'), __('day_thursday'), __('day_friday'), __('day_saturday')])
            ->map(fn ($day, $index) => [
                'day' => $day,
                'score' => (int) round($rows[$index + 1]->score ?? 0),
                'count' => (int) ($rows[$index + 1]->count ?? 0),
            ])
            ->all();
    }

    public function trendData(Builder $query): array
    {
        $now = now();

        $periods = collect(range(11, 0))
            ->map(function ($weeksAgo) use ($now): array {
                $weekEnd = $now->copy()->subWeeks($weeksAgo);
                $weekStart = $weekEnd->copy()->subWeek();

                return [
                    'start' => $weekStart,
                    'end' => $weekEnd,
                ];
            });

        $aggregateQuery = clone $query;

        foreach ($periods as $index => $period) {
            $aggregateQuery
                ->selectRaw(
                    "SUM(CASE WHEN submittedAt >= ? AND submittedAt < ? THEN overallScore ELSE 0 END) as week_{$index}_score_sum",
                    [$period['start'], $period['end']]
                )
                ->selectRaw(
                    "SUM(CASE WHEN submittedAt >= ? AND submittedAt < ? THEN 1 ELSE 0 END) as week_{$index}_count",
                    [$period['start'], $period['end']]
                );
        }

        $row = $aggregateQuery->first();

        return $periods
            ->map(function (array $period, int $index) use ($row): array {
                $scoreSumKey = "week_{$index}_score_sum";
                $countKey = "week_{$index}_count";

                $scoreSum = (float) ($row?->{$scoreSumKey} ?? 0);
                $count = (int) ($row?->{$countKey} ?? 0);

                return [
                    'date' => $period['end']->format('j/n'),
                    'score' => $count > 0 ? (int) round($scoreSum / $count) : 0,
                    'count' => $count,
                ];
            })
            ->values()
            ->all();
    }

    // ─── NPS ───

    public function getNpsScoreForResponses(iterable $responses): int
    {
        $responseIds = [];
        foreach ($responses as $r) {
            $responseIds[] = $r->id;
        }

        return $this->calculateNps($responseIds);
    }

    public function calculateNps(array $responseIds): int
    {
        if (empty($responseIds)) {
            return 0;
        }

        $answers = SurveyAnswer::query()
            ->whereIn('responseId', $responseIds)
            ->whereHas('question', fn ($query) => $query->where('type', 'nps'))
            ->get();

        if ($answers->isEmpty()) {
            return 0;
        }

        $promoters = 0;
        $detractors = 0;
        $total = $answers->count();

        foreach ($answers as $answer) {
            $value = (int) $answer->value;
            if ($value >= 9) {
                $promoters++;
            } elseif ($value <= 6) {
                $detractors++;
            }
        }

        return (int) round((($promoters - $detractors) / $total) * 100);
    }

    public function calculateNpsFromSubQuery(Builder $responseIdSubQuery): int
    {
        // Use SQL aggregation instead of loading all records into PHP memory
        $row = DB::table('survey_answers')
            ->whereIn('responseId', $responseIdSubQuery)
            ->whereIn('questionId', function ($sub) {
                $sub->select('id')
                    ->from('survey_questions')
                    ->where('type', 'nps');
            })
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN CAST(value AS SIGNED) >= 9 THEN 1 ELSE 0 END) as promoters')
            ->selectRaw('SUM(CASE WHEN CAST(value AS SIGNED) <= 6 THEN 1 ELSE 0 END) as detractors')
            ->first();

        $total = (int) ($row?->total ?? 0);
        if ($total === 0) {
            return 0;
        }

        $promoters = (int) ($row?->promoters ?? 0);
        $detractors = (int) ($row?->detractors ?? 0);

        return (int) round((($promoters - $detractors) / $total) * 100);
    }

    // ─── Category Scores ───

    public function categoryScoresFromSubQuery(Builder $responseIdSubQuery): array
    {
        // Use SQL aggregation instead of loading all records into PHP memory
        $rows = DB::table('survey_answers as sa')
            ->whereIn('sa.responseId', $responseIdSubQuery)
            ->join('survey_questions as sq', 'sa.questionId', '=', 'sq.id')
            ->join('survey_sections as ss', 'sq.sectionId', '=', 'ss.id')
            ->whereIn('sq.type', ['stars', 'emoji', 'rating', 'yes_no'])
            ->selectRaw("COALESCE(NULLIF(sq.category, ''), ss.title) as category_label")
            ->selectRaw("SUM(CASE
                WHEN sq.type = 'yes_no' AND sa.value IN ('true','yes','1','5') THEN 5
                WHEN sq.type = 'yes_no' AND sa.value IN ('false','no','0') THEN 0
                WHEN sq.type != 'yes_no' AND sa.value REGEXP '^[0-9]+(\\.[0-9]+)?$' THEN LEAST(5, GREATEST(0, CAST(sa.value AS DECIMAL(5,2))))
                ELSE NULL
            END) as score_sum")
            ->selectRaw("SUM(CASE
                WHEN sq.type = 'yes_no' AND sa.value IN ('true','yes','1','5','false','no','0') THEN 1
                WHEN sq.type != 'yes_no' AND sa.value REGEXP '^[0-9]+(\\.[0-9]+)?$' THEN 1
                ELSE NULL
            END) as score_count")
            ->groupBy('category_label')
            ->havingRaw("category_label IS NOT NULL AND category_label != ''")
            ->get();

        return $rows->map(function ($row) {
            return [
                'category' => (string) $row->category_label,
                'score' => $row->score_count > 0
                    ? (int) round(((float) $row->score_sum / ((float) $row->score_count * 5)) * 100)
                    : 0,
            ];
        })->values()->all();
    }

    // ─── Key Driver ───

    private function predictiveKeyDriver(array $responseIds): string
    {
        if ($responseIds === []) {
            return __('overall_satisfaction_indicator');
        }

        $answers = SurveyAnswer::query()
            ->with('question:id,title,category')
            ->whereIn('responseId', $responseIds)
            ->get(['questionId', 'value']);

        $lowestQuestion = $answers
            ->map(function (SurveyAnswer $answer) {
                $score = $this->normalizeAnswerScore($answer->value);

                if ($score === null) {
                    return null;
                }

                return [
                    'label' => $answer->question?->category ?: $answer->question?->title ?: $answer->questionId,
                    'score' => $score,
                ];
            })
            ->filter()
            ->groupBy('label')
            ->map(fn ($items, string $label) => [
                'label' => $label,
                'score' => $items->avg('score'),
            ])
            ->sortBy('score')
            ->first();

        return $lowestQuestion['label'] ?? __('overall_satisfaction_indicator');
    }

    private function normalizeAnswerScore(mixed $value): ?float
    {
        if (is_bool($value)) {
            return $value ? 100.0 : 0.0;
        }

        $stringValue = is_string($value) ? trim($value) : $value;

        if ($stringValue === 'yes' || $stringValue === 'true') {
            return 100.0;
        }

        if ($stringValue === 'no' || $stringValue === 'false') {
            return 0.0;
        }

        if (! is_numeric($stringValue)) {
            return null;
        }

        $numeric = (float) $stringValue;
        $max = $numeric > 5 ? 10.0 : 5.0;

        return min(100.0, max(0.0, ($numeric / $max) * 100));
    }
}
