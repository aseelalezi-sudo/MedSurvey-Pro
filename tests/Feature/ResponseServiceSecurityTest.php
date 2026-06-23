<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Models\Survey;
use App\Models\SurveySection;
use App\Models\SurveyQuestion;
use App\Services\ResponseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ResponseServiceSecurityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_text_answers_exceeding_max_length_throw_validation_exception(): void
    {
        Settings::query()->updateOrCreate(
            ['id' => 'global'],
            [
                'data' => [
                    'departments' => [
                        ['id' => 'emergency', 'name' => 'Emergency', 'isActive' => true, 'color' => '#ff0000']
                    ]
                ]
            ]
        );

        $survey = Survey::query()->create([
            'id' => 'sec-survey-1',
            'title' => 'Security Survey',
            'description' => 'Test survey description',
            'isActive' => true,
        ]);

        $section = SurveySection::query()->create([
            'surveyId' => $survey->id,
            'title' => 'Sec',
            'description' => 'Test section description',
            'sortOrder' => 1,
        ]);

        SurveyQuestion::query()->create([
            'id' => 'q_sec_1',
            'sectionId' => $section->id,
            'type' => 'text',
            'title' => 'Sec Question',
            'sortOrder' => 1,
            'isRequired' => false,
        ]);

        $service = app(ResponseService::class);

        $longText = str_repeat('A', 1005);

        try {
            $service->store([
                'surveyId' => $survey->id,
                'answers' => [
                    'q_sec_1' => $longText
                ],
                'department' => 'Emergency',
            ]);
            
            $this->fail('ValidationException was not thrown for answer > 1000 characters');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('answers.q_sec_1', $e->errors());
            $this->assertStringContainsString('exceed 1000 characters', $e->errors()['answers.q_sec_1'][0]);
        }
    }
}
