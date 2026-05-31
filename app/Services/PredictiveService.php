<?php

namespace App\Services;

use App\Models\SurveyAnswer;
use Illuminate\Database\Eloquent\Builder;
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
        $responses = (clone $query)
            ->where('submittedAt', '>=', now()->subDays(self::PREDICTIVE_LOOKBACK_DAYS))
            ->orderBy('department')
            ->orderBy('submittedAt')
            ->get(['id', 'department', 'overallScore', 'submittedAt']);

        $alerts = $responses
            ->groupBy('department')
            ->map(function ($departmentResponses, string $department) {
                if ($departmentResponses->count() < self::PREDICTIVE_MIN_DEPARTMENT_RESPONSES) {
                    return null;
                }

                $now = now();
                $currentWindowStart = $now->copy()->subDays(self::PREDICTIVE_WINDOW_DAYS);
                $previousWindowStart = $now->copy()->subDays(self::PREDICTIVE_WINDOW_DAYS * 2);

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

                $previousAvg = (int) round($previousResponses->avg('overallScore'));
                $currentAvg = (int) round($currentResponses->avg('overallScore'));
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
                    'keyDriver' => $this->predictiveKeyDriver($currentResponses->pluck('id')->all()),
                    'sampleCount' => $currentResponses->count() + $previousResponses->count(),
                    'lastResponseDate' => optional($departmentResponses->sortByDesc('submittedAt')->first()?->submittedAt)->toISOString(),
                ];
            })
            ->filter()
            ->sortByDesc('drop')
            ->values();

        return [
            'alerts' => $alerts,
            'stats' => [
                'totalDepts' => (clone $query)
                    ->where('submittedAt', '>=', now()->subDays(self::PREDICTIVE_LOOKBACK_DAYS))
                    ->distinct('department')
                    ->count('department'),
                'activeWarnings' => $alerts->count(),
                'healthIndex' => (int) round((clone $query)
                    ->where('submittedAt', '>=', now()->subDays(self::PREDICTIVE_LOOKBACK_DAYS))
                    ->avg('overallScore') ?? 100),
                'totalResponsesAnalyzed' => (clone $query)
                    ->where('submittedAt', '>=', now()->subDays(self::PREDICTIVE_LOOKBACK_DAYS))
                    ->count(),
            ],
        ];
    }

    // ─── Stats ───

    public function getStats(Builder $query): array
    {
        $totalResponses = (clone $query)->count();
        $averageScore = (int) round((clone $query)->avg('overallScore') ?? 0);

        $previousQuery = clone $query;
        $previousQuery->where('submittedAt', '<', now()->subDays(30));
        $previousAverageScore = (int) round((clone $previousQuery)->avg('overallScore') ?? $averageScore);
        $previousNpsScore = $this->calculateNps((clone $previousQuery)->pluck('id')->all());

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
            ['level' => 'ممتاز', 'count' => (clone $query)->where('overallScore', '>=', 85)->count(), 'color' => '#10B981'],
            ['level' => 'جيد', 'count' => (clone $query)->whereBetween('overallScore', [70, 84])->count(), 'color' => '#3B82F6'],
            ['level' => 'متوسط', 'count' => (clone $query)->whereBetween('overallScore', [50, 69])->count(), 'color' => '#F59E0B'],
            ['level' => 'ضعيف', 'count' => (clone $query)->where('overallScore', '<', 50)->count(), 'color' => '#EF4444'],
        ];

        $responseIdSubQuery = (clone $query)->select('id');

        $now = now();
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $sixtyDaysAgo = $now->copy()->subDays(60);

        $currentVolume = (clone $query)->where('submittedAt', '>=', $thirtyDaysAgo)->count();
        $previousVolume = (clone $query)->whereBetween('submittedAt', [$sixtyDaysAgo, $thirtyDaysAgo])->count();

        $responseRate = 100;
        $previousResponseRate = 100;

        if ($previousVolume > 0) {
            $responseRate = (int) round(($currentVolume / $previousVolume) * 100);
        } elseif ($currentVolume > 0) {
            $responseRate = 100; // From 0 to something is technically infinite growth, we cap at 100% baseline or show 100%
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

        return collect(['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'])
            ->map(fn ($day, $index) => [
                'day' => $day,
                'score' => (int) round($rows[$index + 1]->score ?? 0),
                'count' => (int) ($rows[$index + 1]->count ?? 0),
            ])
            ->all();
    }

    public function trendData(Builder $query): array
    {
        $responses = $query
            ->where('submittedAt', '>=', now()->subDays(84))
            ->get(['overallScore', 'submittedAt']);
        $now = now();

        return collect(range(11, 0))
            ->map(function ($weeksAgo) use ($responses, $now): array {
                $weekEnd = $now->copy()->subWeeks($weeksAgo);
                $weekStart = $weekEnd->copy()->subWeek();
                $weekResponses = $responses->filter(fn ($response) => $response->submittedAt >= $weekStart && $response->submittedAt < $weekEnd);

                return [
                    'date' => $weekEnd->format('j/n'),
                    'score' => $weekResponses->isNotEmpty() ? (int) round($weekResponses->avg('overallScore')) : 0,
                    'count' => $weekResponses->count(),
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
        $answers = SurveyAnswer::query()
            ->whereIn('responseId', $responseIdSubQuery)
            ->whereHas('question', fn ($query) => $query->where('type', 'nps'))
            ->get(['value']);

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

    // ─── Category Scores ───

    public function categoryScoresFromSubQuery(Builder $responseIdSubQuery): array
    {
        $answers = SurveyAnswer::query()
            ->whereIn('responseId', $responseIdSubQuery)
            ->whereHas('question', fn ($query) => $query->whereIn('type', ['stars', 'emoji', 'rating', 'yes_no']))
            ->with(['question.section'])
            ->get();

        $groups = [];
        foreach ($answers as $answer) {
            $question = $answer->question;
            if (! $question) {
                continue;
            }

            $category = $question->category ?: ($question->section?->title ?? null);
            if (! $category) {
                continue;
            }

            $rawValue = $answer->value;
            if ($question->type === 'yes_no') {
                $value = in_array($rawValue, ['true', 'yes', '1', '5'], true) ? 5 : 0;
            } elseif (is_numeric($rawValue)) {
                $value = (float) $rawValue;
            } else {
                continue;
            }

            $groups[$category] ??= ['sum' => 0, 'count' => 0];
            $groups[$category]['sum'] += min(5, max(0, $value));
            $groups[$category]['count']++;
        }

        return collect($groups)
            ->map(fn ($data, $category) => [
                'category' => $category,
                'score' => $data['count'] > 0 ? (int) round(($data['sum'] / ($data['count'] * 5)) * 100) : 0,
            ])
            ->values()
            ->all();
    }

    // ─── Key Driver ───

    private function predictiveKeyDriver(array $responseIds): string
    {
        if ($responseIds === []) {
            return 'مؤشر الرضا العام';
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

        return $lowestQuestion['label'] ?? 'مؤشر الرضا العام';
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
