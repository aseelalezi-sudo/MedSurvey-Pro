<?php

namespace App\Support;

use Illuminate\Support\Collection;

class HallOfFameRanker
{
    private const PRIOR_WEIGHT = 10;

    public static function rank(Collection $departmentScores): Collection
    {
        $globalAverage = (float) $departmentScores->sum(fn ($department) => ((float) ($department['score'] ?? 0)) * ((int) ($department['count'] ?? 0)))
            / max(1, (int) $departmentScores->sum(fn ($department) => (int) ($department['count'] ?? 0)));

        return $departmentScores
            ->map(function (array $department) use ($globalAverage): array {
                $rawScore = (float) ($department['score'] ?? 0);
                $responseCount = (int) ($department['count'] ?? 0);
                $adjustedScore = self::bayesianAdjustedScore($rawScore, $responseCount, $globalAverage);

                return array_merge($department, [
                    'rawScore' => $rawScore,
                    'score' => round($adjustedScore, 1),
                    'adjustedScore' => round($adjustedScore, 1),
                    'globalAverage' => round($globalAverage, 1),
                    'sampleIsLimited' => $responseCount < self::PRIOR_WEIGHT,
                ]);
            })
            ->sortBy([
                ['sampleIsLimited', 'asc'],
                ['adjustedScore', 'desc'],
                ['count', 'desc'],
                ['rawScore', 'desc'],
                ['name', 'asc'],
            ])
            ->values()
            ->map(function (array $department, int $index) use ($departmentScores): array {
                return array_merge($department, [
                    'rank' => $index + 1,
                    'totalRankedDepartments' => $departmentScores->count(),
                ]);
            });
    }

    private static function bayesianAdjustedScore(float $rawScore, int $responseCount, float $globalAverage): float
    {
        if ($responseCount <= 0) {
            return $globalAverage;
        }

        return (($responseCount * $rawScore) + (self::PRIOR_WEIGHT * $globalAverage))
            / ($responseCount + self::PRIOR_WEIGHT);
    }
}
