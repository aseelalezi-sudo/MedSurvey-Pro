<?php

namespace Tests\Feature\Concerns;

use App\Models\Settings;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveySection;
use App\Models\User;

trait CreatesTestData
{
    protected function superAdminUser(): User
    {
        $adminUser = User::query()->where('role', 'super_admin')->first();

        return $adminUser ?? $this->userWithRole('super_admin', [
            'name' => 'Web Test Admin',
            'username' => 'web_test_admin_'.substr(bin2hex(random_bytes(4)), 0, 8),
        ]);
    }

    protected function adminUser(): User
    {
        return $this->userWithRole('admin');
    }

    protected function userWithRole(string $role, array $overrides = []): User
    {
        $suffix = substr(bin2hex(random_bytes(4)), 0, 8);

        return User::query()->create(array_merge([
            'name' => ucfirst(str_replace('_', ' ', $role)).' Test User',
            'username' => $role.'_test_'.$suffix,
            'email' => $role.'_test_'.$suffix.'@example.test',
            'password' => bcrypt('Password123!'),
            'role' => $role,
            'department' => null,
            'isActive' => true,
        ], $overrides));
    }

    protected function createUserForRole(string $role, ?string $department = null): User
    {
        return $this->userWithRole($role, [
            'department' => $department,
        ]);
    }

    private function ensureSurveyExists(): Survey
    {
        $survey = Survey::query()->first();
        if (! $survey) {
            $survey = Survey::query()->create([
                'id' => 'test-survey-'.substr(bin2hex(random_bytes(4)), 0, 8),
                'title' => 'Test Survey',
                'description' => 'Test Description',
                'isActive' => true,
            ]);
        }

        return $survey;
    }

    private function createActiveSurveyWithQuestion(): Survey
    {
        $settings = Settings::query()->first();
        if (! $settings) {
            Settings::query()->create([
                'data' => [
                    'departments' => [
                        ['name' => 'Emergency', 'isActive' => true],
                        ['name' => 'Pharmacy', 'isActive' => true],
                        ['name' => 'Cardiology', 'isActive' => true],
                    ],
                ],
            ]);
        } else {
            $data = $settings->data;
            $data['departments'] = [
                ['name' => 'Emergency', 'isActive' => true],
                ['name' => 'Pharmacy', 'isActive' => true],
                ['name' => 'Cardiology', 'isActive' => true],
            ];
            $settings->update(['data' => $data]);
        }

        $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
        $survey = Survey::query()->create([
            'id' => 'survey-flow-'.$suffix,
            'title' => 'Flow Test Survey '.$suffix,
            'description' => 'Test description',
            'isActive' => true,
            'requireName' => false,
            'requirePhone' => false,
        ]);

        $section = SurveySection::query()->create([
            'id' => 'section-flow-'.$suffix,
            'surveyId' => $survey->id,
            'title' => 'Main Section',
            'description' => 'Section description',
            'sortOrder' => 1,
        ]);

        SurveyQuestion::query()->create([
            'id' => 'question-flow-'.$suffix,
            'sectionId' => $section->id,
            'type' => 'stars',
            'title' => 'How satisfied are you?',
            'required' => true,
            'sortOrder' => 1,
        ]);

        $survey->load('sections.questions');

        return $survey;
    }

    private function validStorePayload(Survey $survey, string $department = 'Emergency', array $overrides = []): array
    {
        $question = $survey->sections->first()->questions->first();

        return array_merge([
            '_startedAt' => (int) ((microtime(true) - 10) * 1000),
            'surveyId' => $survey->id,
            'answers' => [
                [
                    'questionId' => $question->id,
                    'value' => 5,
                ],
            ],
            'department' => $department,
            'patientInfo' => [
                'name' => 'Test Patient',
                'phone' => '0555123456',
                'ageGroup' => '30-39',
                'gender' => 'male',
                'visitType' => 'clinic',
            ],
        ], $overrides);
    }

    private function createTestSurvey(): Survey
    {
        return Survey::query()->firstOrCreate(
            ['id' => 'test-survey-filter'],
            [
                'title' => 'Filter Test Survey',
                'description' => 'Test',
                'isActive' => true,
            ]
        );
    }
}
