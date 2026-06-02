<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SurveyManagementControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_create_update_toggle_duplicate_and_delete_survey(): void
    {
        $admin = $this->adminUser();
        $payload = $this->surveyPayload('Controller Managed Survey');

        $createResponse = $this->actingAs($admin)->post(route('dashboard.surveys.store'), $payload);

        $createResponse->assertRedirect();
        $survey = Survey::query()->where('title', 'Controller Managed Survey')->firstOrFail();
        $this->assertTrue($survey->isActive);
        $this->assertEquals(['Emergency', 'Pharmacy'], $survey->assignedDepartments);
        $this->assertEquals(['first tip'], $survey->tips);
        $this->assertDatabaseHas('survey_sections', ['surveyId' => $survey->id, 'title' => 'Care Experience']);
        $this->assertDatabaseHas('survey_questions', ['title' => 'How was the care?', 'type' => 'stars']);

        $updateResponse = $this->actingAs($admin)->put(route('dashboard.surveys.update', $survey), $this->surveyPayload('Updated Survey Title', [
            'description' => 'Updated description',
            'isActive' => false,
            'sections' => [
                [
                    'title' => 'Updated Section',
                    'description' => 'Updated section description',
                    'icon' => 'activity',
                    'questions' => [
                        [
                            'type' => 'nps',
                            'title' => 'Would you recommend us?',
                            'required' => true,
                            'category' => 'recommendation',
                        ],
                    ],
                ],
            ],
        ]));

        $updateResponse->assertRedirect();
        $survey->refresh();
        $this->assertSame('Updated Survey Title', $survey->title);
        $this->assertFalse($survey->isActive);
        $this->assertDatabaseHas('survey_sections', ['surveyId' => $survey->id, 'title' => 'Updated Section']);
        $this->assertDatabaseHas('survey_questions', ['title' => 'Would you recommend us?', 'type' => 'nps']);

        $toggleResponse = $this->actingAs($admin)->patch(route('dashboard.surveys.toggle', $survey));
        $toggleResponse->assertRedirect();
        $this->assertTrue($survey->fresh()->isActive);

        $duplicateResponse = $this->actingAs($admin)->post(route('dashboard.surveys.duplicate', $survey));
        $duplicateResponse->assertRedirect();
        $this->assertDatabaseHas('surveys', ['title' => 'Updated Survey Title - نسخة']);

        $deleteResponse = $this->actingAs($admin)->delete(route('dashboard.surveys.destroy', $survey));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('surveys', ['id' => $survey->id]);
    }

    public function test_tenant_admin_cannot_duplicate_survey_from_another_tenant(): void
    {
        $tenantA = Tenant::query()->create(['name' => 'Tenant A']);
        $tenantB = Tenant::query()->create(['name' => 'Tenant B']);
        $admin = $this->adminUser($tenantA->id);
        $otherSurvey = Survey::query()->create([
            'title' => 'Other Tenant Survey',
            'description' => 'Private',
            'tenantId' => $tenantB->id,
            'isActive' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('dashboard.surveys.duplicate', $otherSurvey));

        $response->assertRedirect();
        $this->assertDatabaseMissing('surveys', ['title' => 'Other Tenant Survey - نسخة']);
    }

    private function surveyPayload(string $title, array $overrides = []): array
    {
        return array_replace_recursive([
            'title' => $title,
            'description' => 'Managed by feature test',
            'isActive' => true,
            'requireName' => true,
            'requirePhone' => false,
            'assignedDepartments' => ['Emergency', 'Pharmacy', 'Emergency'],
            'tips' => ['first tip', '', null],
            'sections' => [
                [
                    'title' => 'Care Experience',
                    'description' => 'Care section',
                    'icon' => 'heart-pulse',
                    'questions' => [
                        [
                            'type' => 'stars',
                            'title' => 'How was the care?',
                            'required' => true,
                            'category' => 'care',
                        ],
                    ],
                ],
            ],
        ], $overrides);
    }

    private function adminUser(?string $tenantId = null): User
    {
        return User::query()->create([
            'username' => 'survey_admin_'.bin2hex(random_bytes(4)),
            'password' => bcrypt('Password123!'),
            'name' => 'Survey Admin',
            'role' => 'admin',
            'tenantId' => $tenantId,
            'isActive' => true,
        ]);
    }
}
