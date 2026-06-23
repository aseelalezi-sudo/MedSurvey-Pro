<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Services\PredictiveService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PredictiveServiceStatsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_stats_returns_satisfaction_distribution_counts(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $survey = Survey::query()->create([
            'id' => 'stats-survey-'.$suffix,
            'title' => 'Stats Survey',
            'description' => 'Survey used for predictive stats tests.',
            'isActive' => true,
            'requireName' => false,
            'requirePhone' => false,
            'assignedDepartments' => ['Stats Department'],
            'tips' => null,
            'tenantId' => null,
        ]);

        $this->createResponse($survey->id, 'excellent-1-'.$suffix, 95);
        $this->createResponse($survey->id, 'excellent-2-'.$suffix, 85);
        $this->createResponse($survey->id, 'good-1-'.$suffix, 84);
        $this->createResponse($survey->id, 'good-2-'.$suffix, 70);
        $this->createResponse($survey->id, 'average-1-'.$suffix, 69);
        $this->createResponse($survey->id, 'average-2-'.$suffix, 50);
        $this->createResponse($survey->id, 'poor-1-'.$suffix, 49);
        $this->createResponse($survey->id, 'poor-2-'.$suffix, 20);

        $stats = app(PredictiveService::class)->getStats(SurveyResponse::query()->where('department', 'Stats Department'));

        $distribution = collect($stats['satisfactionDistribution'])->keyBy('level');

        $this->assertSame(2, $distribution['score_excellent']['count']);
        $this->assertSame(2, $distribution['score_good']['count']);
        $this->assertSame(2, $distribution['score_average']['count']);
        $this->assertSame(2, $distribution['score_poor']['count']);
    }

    public function test_trend_data_returns_weekly_score_and_count_buckets(): void
    {
        $survey = Survey::query()->create([
            'title' => 'Trend Survey',
            'description' => 'Survey used for predictive trend tests.',
            'isActive' => true,
            'requireName' => false,
            'requirePhone' => false,
            'assignedDepartments' => ['Trend Department'],
            'tips' => null,
            'tenantId' => null,
        ]);

        $this->createResponse($survey->id, 'trend-old-week', 70, now()->subWeeks(11)->subDays(3));
        $this->createResponse($survey->id, 'trend-current-week-1', 60, now()->subDays(3));
        $this->createResponse($survey->id, 'trend-current-week-2', 100, now()->subDays(2));

        $trendData = app(PredictiveService::class)->trendData(
            SurveyResponse::query()->where('surveyId', $survey->id)
        );

        $this->assertCount(12, $trendData);

        $this->assertSame(70, $trendData[0]['score']);
        $this->assertSame(1, $trendData[0]['count']);

        $this->assertSame(80, $trendData[11]['score']);
        $this->assertSame(2, $trendData[11]['count']);
    }

    private function createResponse(string $surveyId, string $id, int $score, mixed $submittedAt = null): void
    {
        SurveyResponse::query()->create([
            'id' => "stats-response-{$id}",
            'surveyId' => $surveyId,
            'answers' => ['overall' => $score],
            'patientName' => null,
            'patientPhone' => null,
            'ageGroup' => null,
            'gender' => null,
            'visitType' => null,
            'department' => 'Stats Department',
            'overallScore' => $score,
            'submittedAt' => $submittedAt ?? now(),
            'tenantId' => null,
        ]);
    }
}
