<?php

namespace Tests\Unit;

use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Services\PredictiveService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

class PredictiveServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PredictiveService $predictiveService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->predictiveService = app(PredictiveService::class);
    }

    /**
     * Test the private normalizeAnswerScore method using reflection.
     */
    public function test_normalize_answer_score(): void
    {
        $reflection = new ReflectionMethod(PredictiveService::class, 'normalizeAnswerScore');
        $reflection->setAccessible(true);

        // Booleans
        $this->assertEquals(100.0, $reflection->invoke($this->predictiveService, true));
        $this->assertEquals(0.0, $reflection->invoke($this->predictiveService, false));

        // Strings
        $this->assertEquals(100.0, $reflection->invoke($this->predictiveService, 'yes'));
        $this->assertEquals(0.0, $reflection->invoke($this->predictiveService, 'no'));
        $this->assertEquals(100.0, $reflection->invoke($this->predictiveService, 'true'));
        $this->assertEquals(0.0, $reflection->invoke($this->predictiveService, 'false'));

        // Scale <= 5 (should normalize against max 5)
        $this->assertEquals(60.0, $reflection->invoke($this->predictiveService, 3)); // 3/5 = 60%
        $this->assertEquals(100.0, $reflection->invoke($this->predictiveService, 5)); // 5/5 = 100%

        // Scale > 5 (should normalize against max 10)
        $this->assertEquals(80.0, $reflection->invoke($this->predictiveService, 8)); // 8/10 = 80%
        $this->assertEquals(90.0, $reflection->invoke($this->predictiveService, 9)); // 9/10 = 90%

        // Invalid inputs
        $this->assertNull($reflection->invoke($this->predictiveService, 'invalid-value'));
    }

    public function test_calculate_nps_empty_responses(): void
    {
        $score = $this->predictiveService->calculateNps([]);
        $this->assertEquals(0, $score);
    }

    public function test_get_alerts_empty_query_returns_healthy_defaults(): void
    {
        $alertsData = $this->predictiveService->getAlerts(SurveyResponse::query()->where('id', 'missing-response-id'));

        $this->assertCount(0, $alertsData['alerts']);
        $this->assertSame(0, $alertsData['stats']['totalDepts']);
        $this->assertSame(0, $alertsData['stats']['activeWarnings']);
        $this->assertSame(100, $alertsData['stats']['healthIndex']);
        $this->assertSame(0, $alertsData['stats']['totalResponsesAnalyzed']);
    }

    public function test_calculate_nps_with_answers(): void
    {
        // 1. Create parent Survey and SurveySection records to satisfy foreign key constraints
        Survey::query()->create([
            'id' => 'survey-1',
            'title' => 'Test Survey',
            'description' => 'Test Description',
            'isActive' => true,
        ]);

        SurveySection::query()->create([
            'id' => 'section-1',
            'surveyId' => 'survey-1',
            'title' => 'Test Section',
            'description' => 'Test Section Description',
        ]);

        // 2. Create a dummy survey response and question
        $response = SurveyResponse::query()->create([
            'id' => 'test-resp-nps-1',
            'surveyId' => 'survey-1',
            'answers' => [],
            'department' => 'Test Department',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);

        $question = SurveyQuestion::query()->create([
            'id' => 'test-quest-nps-1',
            'surveyId' => 'survey-1',
            'sectionId' => 'section-1',
            'type' => 'nps',
            'title' => 'NPS Question',
            'isRequired' => true,
        ]);

        // Promoters (9-10) -> count 1
        SurveyAnswer::query()->create([
            'responseId' => $response->id,
            'questionId' => $question->id,
            'value' => '10',
        ]);

        // Detractors (0-6) -> count 0
        // Passive (7-8) -> count 0
        // NPS should be (1 promoter - 0 detractors) / 1 total = 100%
        $score1 = $this->predictiveService->calculateNps([$response->id]);
        $this->assertEquals(100, $score1);

        // Add a detractor response
        $response2 = SurveyResponse::query()->create([
            'id' => 'test-resp-nps-2',
            'surveyId' => 'survey-1',
            'answers' => [],
            'department' => 'Test Department',
            'overallScore' => 40,
            'submittedAt' => now(),
        ]);

        SurveyAnswer::query()->create([
            'responseId' => $response2->id,
            'questionId' => $question->id,
            'value' => '5', // Detractor
        ]);

        // NPS should be (1 promoter - 1 detractor) / 2 total = 0%
        $score2 = $this->predictiveService->calculateNps([$response->id, $response2->id]);
        $this->assertEquals(0, $score2);
    }

    public function test_get_alerts_detects_department_drop(): void
    {
        Survey::query()->create([
            'id' => 'survey-alerts-1',
            'title' => 'Alerts Survey',
            'description' => 'Test Description',
            'isActive' => true,
        ]);

        foreach ([92, 88] as $index => $score) {
            SurveyResponse::query()->create([
                'id' => 'alerts-prev-'.$index,
                'surveyId' => 'survey-alerts-1',
                'answers' => [],
                'department' => 'Emergency',
                'overallScore' => $score,
                'submittedAt' => now()->subDays(10 + $index),
            ]);
        }

        foreach ([62, 58] as $index => $score) {
            SurveyResponse::query()->create([
                'id' => 'alerts-current-'.$index,
                'surveyId' => 'survey-alerts-1',
                'answers' => [],
                'department' => 'Emergency',
                'overallScore' => $score,
                'submittedAt' => now()->subDays(1 + $index),
            ]);
        }

        $alertsData = $this->predictiveService->getAlerts(SurveyResponse::query()->where('surveyId', 'survey-alerts-1'));

        $this->assertSame(1, $alertsData['stats']['activeWarnings']);
        $this->assertSame('Emergency', $alertsData['alerts'][0]['department']);
        $this->assertSame(90, $alertsData['alerts'][0]['previousAvg']);
        $this->assertSame(60, $alertsData['alerts'][0]['currentAvg']);
        $this->assertSame(30, $alertsData['alerts'][0]['drop']);
    }
}
