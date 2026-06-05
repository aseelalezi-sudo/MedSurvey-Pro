<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveySection;
use App\Services\ResponseService;
use App\Support\DashboardAnalyticsCache;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ResponseServiceCacheInvalidationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_storing_survey_response_bumps_dashboard_analytics_cache_version(): void
    {
        Cache::flush();

        $settings = Settings::query()->first();
        if ($settings) {
            $data = $settings->data;
            $departments = $data['departments'] ?? [];
            $departments[] = ['name' => 'Cache Department', 'isActive' => true];
            $data['departments'] = $departments;
            $settings->update(['data' => $data]);
        } else {
            Settings::query()->create([
                'id' => 'global',
                'data' => [
                    'departments' => [
                        ['name' => 'Cache Department', 'isActive' => true],
                    ],
                ],
            ]);
        }

        $survey = Survey::query()->create([
            'title' => 'Cache Invalidation Survey',
            'description' => 'Survey used to verify dashboard cache invalidation.',
            'isActive' => true,
            'requireName' => false,
            'requirePhone' => false,
            'assignedDepartments' => ['Cache Department'],
            'tips' => null,
            'tenantId' => null,
        ]);

        $section = SurveySection::query()->create([
            'surveyId' => $survey->id,
            'title' => 'General',
            'description' => 'General feedback section.',
            'icon' => 'clipboard-check',
            'sortOrder' => 1,
        ]);

        $question = SurveyQuestion::query()->create([
            'sectionId' => $section->id,
            'type' => 'rating',
            'title' => 'How satisfied are you?',
            'description' => null,
            'required' => true,
            'category' => 'Overall',
            'options' => null,
            'followUp' => null,
            'sortOrder' => 1,
        ]);

        $beforeKey = DashboardAnalyticsCache::key(null, 'response_service_cache_test');

        $response = app(ResponseService::class)->store([
            'surveyId' => $survey->id,
            'answers' => [
                $question->id => 5,
            ],
            'department' => 'Cache Department',
            'patientInfo' => [
                'name' => 'Cache Test Patient',
                'phone' => '777000111',
                'ageGroup' => '18 - 30 سنة',
                'gender' => 'male',
                'visitType' => 'مراجعة',
            ],
        ]);

        $afterKey = DashboardAnalyticsCache::key(null, 'response_service_cache_test');

        $this->assertNotSame($beforeKey, $afterKey);
    }
}
