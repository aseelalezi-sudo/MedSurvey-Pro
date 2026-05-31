<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Concerns\CreatesTestData;
use Tests\TestCase;

class PublicSurveyFlowTest extends TestCase
{
    use CreatesTestData;
    use DatabaseTransactions;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::query()->where('role', 'super_admin')->first();
        if (! $this->adminUser) {
            $this->adminUser = User::query()->create([
                'username' => 'web_test_admin',
                'password' => bcrypt('password123'),
                'name' => 'Web Test Admin',
                'role' => 'super_admin',
                'isActive' => true,
            ]);
        }
    }

    public function test_survey_selection_page_loads(): void
    {
        $this->get(route('survey.selection'))->assertOk();
    }

    public function test_survey_info_redirects_to_selection(): void
    {
        $this->get(route('survey.info'))->assertRedirect(route('survey.selection'));
    }

    public function test_survey_taking_page_loads_for_valid_active_survey(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();

        $this->get(route('survey.take', ['surveyId' => $survey->id]))
            ->assertOk()
            ->assertSee($survey->title);
    }

    public function test_survey_take_redirects_without_survey_id(): void
    {
        $this->get(route('survey.take'))
            ->assertRedirect(route('survey.selection'));
    }

    public function test_survey_take_returns_404_for_inactive_survey(): void
    {
        $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
        $survey = Survey::query()->create([
            'id' => 'inactive-survey-'.$suffix,
            'title' => 'Inactive Survey',
            'description' => 'Test description',
            'isActive' => false,
        ]);

        $this->get(route('survey.take', ['surveyId' => $survey->id]))
            ->assertNotFound();
    }

    public function test_valid_survey_submission_creates_survey_response(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();
        $payload = $this->validStorePayload($survey);

        $response = $this->postJson(route('survey.responses'), $payload);

        $response->assertCreated();
        $response->assertJsonStructure([
            'id',
            'surveyId',
            'answers',
            'patientInfo',
            'submittedAt',
            'department',
            'overallScore',
        ]);

        $this->assertEquals($survey->id, $response->json('surveyId'));

        $this->assertDatabaseHas('survey_responses', [
            'surveyId' => $survey->id,
            'patientName' => 'Test Patient',
            'department' => 'Emergency',
        ]);
    }

    public function test_required_validation_returns_error(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();

        $response = $this->postJson(route('survey.responses'), [
            '_startedAt' => (int) ((microtime(true) - 10) * 1000),
            'surveyId' => $survey->id,
            'answers' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_honeypot_anti_bot_accepts_without_storing(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();
        $payload = $this->validStorePayload($survey, 'Emergency', [
            '_website' => 'spam-value',
        ]);

        $response = $this->postJson(route('survey.responses'), $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'id' => 'ok',
            'message' => 'Response recorded',
        ]);

        $this->assertEquals(0, SurveyResponse::query()->where('surveyId', $survey->id)->count());
    }

    public function test_timing_anti_bot_rejects_fast_submissions(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();
        $payload = $this->validStorePayload($survey, 'Emergency', [
            '_startedAt' => (int) ((microtime(true) - 0.1) * 1000),
        ]);

        $response = $this->postJson(route('survey.responses'), $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'id' => 'ok',
            'message' => 'Response recorded',
        ]);

        $this->assertEquals(0, SurveyResponse::query()->where('surveyId', $survey->id)->count());
    }

    public function test_anonymous_submission_works_when_survey_allows(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();
        $payload = $this->validStorePayload($survey, 'Emergency', [
            'patientInfo' => [
                'name' => '',
                'phone' => '',
            ],
        ]);

        $response = $this->postJson(route('survey.responses'), $payload);

        $response->assertCreated();
        $this->assertEquals('', $response->json('patientInfo.name'));
        $this->assertEquals('', $response->json('patientInfo.phone'));
    }

    public function test_name_phone_required_behavior(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();

        $payload = $this->validStorePayload($survey, 'Emergency', [
            'patientInfo' => null,
        ]);

        $response = $this->postJson(route('survey.responses'), $payload);

        $response->assertCreated();
        $this->assertEmpty($response->json('patientInfo.name'));
    }

    public function test_thank_you_page_loads(): void
    {
        $this->get(route('survey.thanks'))->assertOk();
    }

    public function test_invalid_department_returns_validation_error(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();
        $payload = $this->validStorePayload($survey, 'NonExistentDepartment');

        $response = $this->postJson(route('survey.responses'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['department']);
    }
}
