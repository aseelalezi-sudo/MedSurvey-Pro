<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
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

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->adminUser = $this->superAdminUser();
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

    public function test_global_survey_settings_require_patient_name_and_phone(): void
    {
        $this->setSurveySettings([
            'allowAnonymous' => false,
            'requireName' => true,
            'requirePhone' => true,
        ]);

        $survey = $this->createActiveSurveyWithQuestion();
        $payload = $this->validStorePayload($survey, 'Emergency', [
            'patientInfo' => [
                'name' => '',
                'phone' => '',
                'ageGroup' => '30-39',
                'gender' => 'male',
                'visitType' => 'clinic',
            ],
        ]);

        $response = $this->postJson(route('survey.responses'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['patientInfo.name', 'patientInfo.phone']);
    }

    public function test_global_survey_settings_can_require_all_questions(): void
    {
        $this->setSurveySettings([
            'allowAnonymous' => true,
            'requireAllQuestions' => true,
        ]);

        $survey = $this->createActiveSurveyWithQuestion();
        $section = $survey->sections->first();
        SurveyQuestion::query()->create([
            'id' => 'question-optional-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'sectionId' => $section->id,
            'type' => 'text',
            'title' => 'Optional comment',
            'required' => false,
            'sortOrder' => 2,
        ]);
        $survey->load('sections.questions');

        $response = $this->postJson(route('survey.responses'), $this->validStorePayload($survey));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['answers']);
    }

    public function test_global_survey_settings_can_disable_thank_you_page(): void
    {
        $this->setSurveySettings([
            'allowAnonymous' => true,
            'enableThankYouPage' => false,
        ]);

        $survey = $this->createActiveSurveyWithQuestion();
        $response = $this->postJson(route('survey.responses'), $this->validStorePayload($survey));

        $response->assertCreated();
        $this->assertSame(route('home'), $response->json('redirectUrl'));
    }

    public function test_global_survey_settings_custom_thank_you_message_is_displayed(): void
    {
        $this->setSurveySettings([
            'allowAnonymous' => true,
            'enableThankYouPage' => true,
            'thankYouMessage' => 'Custom thank you message',
        ]);

        $survey = $this->createActiveSurveyWithQuestion();
        $this->postJson(route('survey.responses'), $this->validStorePayload($survey))
            ->assertCreated()
            ->assertJson(['redirectUrl' => route('survey.thanks')]);

        $this->get(route('survey.thanks'))
            ->assertOk()
            ->assertSee('Custom thank you message');
    }

    public function test_global_survey_settings_can_hide_progress_bar(): void
    {
        $this->setSurveySettings([
            'allowAnonymous' => true,
            'showProgressBar' => false,
        ]);

        $survey = $this->createActiveSurveyWithQuestion();

        $this->get(route('survey.take', ['surveyId' => $survey->id]))
            ->assertOk()
            ->assertSee('x-show="false"', false);
    }

    public function test_appearance_setting_can_hide_public_language_toggle(): void
    {
        Settings::query()->updateOrCreate(
            ['id' => 'global'],
            [
                'tenantId' => null,
                'data' => [
                    'appearance' => [
                        'showLanguageToggle' => false,
                    ],
                    'departments' => [
                        ['name' => 'Emergency', 'isActive' => true],
                    ],
                ],
            ]
        );

        $survey = $this->createActiveSurveyWithQuestion();

        $this->get(route('survey.selection'))
            ->assertOk()
            ->assertDontSee('set-locale');

        $this->get(route('survey.take', ['surveyId' => $survey->id]))
            ->assertOk()
            ->assertDontSee('set-locale');
    }

    public function test_yes_no_question_uses_visible_selected_button_colors(): void
    {
        $markup = file_get_contents(resource_path('views/survey/take.blade.php'));

        $this->assertStringContainsString('bg-teal-600 text-white border-teal-600', $markup);
        $this->assertStringContainsString('bg-red-600 text-white border-red-600', $markup);
        $this->assertStringNotContainsString('bg-teal-650', $markup);
        $this->assertStringNotContainsString('border-red-550', $markup);
    }

    public function test_emoji_question_selected_state_uses_colored_box(): void
    {
        $styles = file_get_contents(resource_path('css/app.css'));

        $this->assertStringNotContainsString('.survey-emoji-button.is-active::after', $styles);
        $this->assertStringContainsString('background: linear-gradient(135deg, rgb(254 226 226), rgb(252 165 165));', $styles);
        $this->assertStringContainsString('background: linear-gradient(135deg, rgb(220 252 231), rgb(134 239 172));', $styles);
        $this->assertStringContainsString('.survey-emoji-button.is-active.emoji-shadow-red:hover', $styles);
        $this->assertStringContainsString('.survey-emoji-button.is-active.emoji-shadow-green:hover', $styles);
        $this->assertStringContainsString('.dark .survey-emoji-button.is-active.emoji-shadow-red', $styles);
        $this->assertStringContainsString('.dark .survey-emoji-button.is-active.emoji-shadow-green', $styles);
        $this->assertStringContainsString('border-width: 3px;', $styles);
    }

    public function test_survey_submission_validates_department_against_survey_tenant_settings(): void
    {
        DB::table('tenants')->insertOrIgnore([
            'id' => 'tenant-1',
        ]);
        
        config(['medsurvey.allow_public_tenant_query' => true]);

        Settings::query()->updateOrCreate(
            ['id' => 'global'],
            [
                'tenantId' => null,
                'data' => array_replace_recursive(app(SettingsService::class)->defaults(), [
                    'departments' => [
                        ['id' => 'global-dept', 'name' => 'Global Emergency', 'isActive' => true, 'color' => '#EF4444'],
                    ],
                ]),
            ]
        );

        Settings::query()->create([
            'id' => 'tenant-settings-1',
            'tenantId' => 'tenant-1',
            'data' => array_replace_recursive(app(SettingsService::class)->defaults(), [
                'departments' => [
                    ['id' => 'tenant-dept', 'name' => 'Tenant Cardiology', 'isActive' => true, 'color' => '#10B981'],
                ],
            ]),
        ]);

        $survey = Survey::query()->create([
            'title' => 'Tenant Department Survey',
            'description' => 'Survey used to test tenant-aware department validation.',
            'isActive' => true,
            'requireName' => false,
            'requirePhone' => false,
            'assignedDepartments' => ['Tenant Cardiology'],
            'tips' => null,
            'tenantId' => 'tenant-1',
        ]);

        $section = SurveySection::query()->create([
            'surveyId' => $survey->id,
            'title' => 'General',
            'description' => 'Section description',
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

        $this->postJson(route('survey.responses'), [
            'surveyId' => $survey->id,
            'tenantId' => 'tenant-1',
            'department' => 'Global Emergency',
            'patientInfo' => [
                'name' => '',
                'phone' => '',
                'ageGroup' => '',
                'gender' => '',
                'visitType' => '',
            ],
            'answers' => [
                [
                    'questionId' => $question->id,
                    'value' => 5,
                ],
            ],
            'startedAt' => now()->subSeconds(10)->timestamp,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['department']);

        $this->postJson(route('survey.responses'), [
            'surveyId' => $survey->id,
            'tenantId' => 'tenant-1',
            'department' => 'Tenant Cardiology',
            'patientInfo' => [
                'name' => '',
                'phone' => '',
                'ageGroup' => '',
                'gender' => '',
                'visitType' => '',
            ],
            'answers' => [
                [
                    'questionId' => $question->id,
                    'value' => 5,
                ],
            ],
            'startedAt' => now()->subSeconds(10)->timestamp,
        ])->assertCreated();

        $this->assertDatabaseHas('survey_responses', [
            'surveyId' => $survey->id,
            'department' => 'Tenant Cardiology',
            'tenantId' => 'tenant-1',
        ]);

        $this->assertDatabaseMissing('survey_responses', [
            'surveyId' => $survey->id,
            'department' => 'Global Emergency',
        ]);
    }

    public function test_public_survey_selection_respects_public_tenant_id(): void
    {
        config(['medsurvey.public_tenant_id' => 'tenant-public-a']);

        Tenant::query()->firstOrCreate(
            ['id' => 'tenant-public-a'],
            ['name' => 'Tenant Public A']
        );

        Tenant::query()->firstOrCreate(
            ['id' => 'tenant-public-b'],
            ['name' => 'Tenant Public B']
        );

        $visibleSurvey = Survey::query()->create([
            'id' => 'public-visible-survey',
            'title' => 'Visible Public Survey',
            'description' => 'Visible',
            'isActive' => true,
            'tenantId' => 'tenant-public-a',
        ]);

        Survey::query()->create([
            'id' => 'public-hidden-survey',
            'title' => 'Hidden Public Survey',
            'description' => 'Hidden',
            'isActive' => true,
            'tenantId' => 'tenant-public-b',
        ]);

        $response = $this->get(route('survey.selection'));

        $response->assertOk();
        $response->assertSee($visibleSurvey->title);
        $response->assertDontSee('Hidden Public Survey');
    }

    public function test_public_survey_take_rejects_survey_outside_public_tenant_id(): void
    {
        config(['medsurvey.public_tenant_id' => 'tenant-public-a']);

        Tenant::query()->firstOrCreate(
            ['id' => 'tenant-public-a'],
            ['name' => 'Tenant Public A']
        );

        Tenant::query()->firstOrCreate(
            ['id' => 'tenant-public-b'],
            ['name' => 'Tenant Public B']
        );

        Survey::query()->create([
            'id' => 'public-hidden-take-survey',
            'title' => 'Hidden Take Survey',
            'description' => 'Hidden',
            'isActive' => true,
            'tenantId' => 'tenant-public-b',
        ]);

        $response = $this->get(route('survey.take', [
            'surveyId' => 'public-hidden-take-survey',
        ]));

        $response->assertNotFound();
    }

    private function setSurveySettings(array $surveySettings): void
    {
        Settings::query()->updateOrCreate(
            ['id' => 'global'],
            [
                'tenantId' => null,
                'data' => [
                    'departments' => [
                        ['name' => 'Emergency', 'isActive' => true],
                    ],
                    'surveySettings' => $surveySettings,
                ],
            ]
        );
    }
}
