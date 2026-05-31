<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WebViewsTest extends TestCase
{
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

    private function createUserForRole(string $role, ?string $department = null): User
    {
        return User::query()->create([
            'name' => ucfirst(str_replace('_', ' ', $role)).' Test User',
            'username' => $role.'_test_'.substr(bin2hex(random_bytes(4)), 0, 8),
            'email' => $role.'_test_'.substr(bin2hex(random_bytes(4)), 0, 8).'@example.test',
            'password' => bcrypt('Password123!'),
            'role' => $role,
            'department' => $department,
            'isActive' => true,
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

    /**
     * Create a survey with a section and a stars question, seeded with settings
     * that include the given department.
     */
    private function createActiveSurveyWithQuestion(string $department = 'Emergency'): Survey
    {
        // Ensure settings exist with the department
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

        // Refresh relation for subsequent use
        $survey->load('sections.questions');

        return $survey;
    }

    /**
     * Build a valid store payload for the given survey.
     * The _startedAt is set far enough in the past to bypass the anti-bot timer.
     */
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

    // ──────────────────────────────────────────────
    //  Public pages
    // ──────────────────────────────────────────────

    public function test_public_pages_load(): void
    {
        $this->get(route('home'))->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('survey.selection'))->assertOk();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_dashboard_pages_load(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('dashboard.index'));
        $response->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.responses'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.reports'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.predictive'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.tickets'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.hall-of-fame'))
            ->assertOk();
    }

    public function test_admin_only_dashboard_pages_load(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('dashboard.surveys'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.users'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.settings'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.audit'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.monitoring'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.error-logs'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.backups'))
            ->assertOk();
    }

    // ──────────────────────────────────────────────
    //  Survey public flow
    // ──────────────────────────────────────────────

    public function test_survey_selection_page_loads(): void
    {
        $this->get(route('survey.selection'))->assertOk();
    }

    public function test_survey_info_redirects_to_selection(): void
    {
        // The info() method simply redirects to survey.selection
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

        // Verify database
        $this->assertDatabaseHas('survey_responses', [
            'surveyId' => $survey->id,
            'patientName' => 'Test Patient',
            'department' => 'Emergency',
        ]);
    }

    public function test_required_validation_returns_error(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();

        // Submit without department (required field)
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

        // Bot gets fake success
        $response->assertStatus(201);
        $response->assertJson([
            'id' => 'ok',
            'message' => 'Response recorded',
        ]);

        // No response was actually stored
        $this->assertEquals(0, SurveyResponse::query()->where('surveyId', $survey->id)->count());
    }

    public function test_timing_anti_bot_rejects_fast_submissions(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();
        $payload = $this->validStorePayload($survey, 'Emergency', [
            // Simulate submission in under 5 seconds (100ms)
            '_startedAt' => (int) ((microtime(true) - 0.1) * 1000),
        ]);

        $response = $this->postJson(route('survey.responses'), $payload);

        // Fast submission gets fake success
        $response->assertStatus(201);
        $response->assertJson([
            'id' => 'ok',
            'message' => 'Response recorded',
        ]);

        // No response was actually stored
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
        // Note: requireName/requirePhone on the Survey model are NOT enforced
        // by the backend store validation. The controller always accepts them
        // as nullable. These fields exist on the model for UI-level use.
        // This test documents current behavior.
        $survey = $this->createActiveSurveyWithQuestion();

        // Submit without patientInfo at all
        $payload = $this->validStorePayload($survey, 'Emergency', [
            'patientInfo' => null,
        ]);

        $response = $this->postJson(route('survey.responses'), $payload);

        // Backend accepts it because patientInfo is nullable
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

    // ──────────────────────────────────────────────
    //  Admin AJAX
    // ──────────────────────────────────────────────

    public function test_admin_can_filter_responses_via_ajax(): void
    {
        $survey = Survey::query()->first();
        if (! $survey) {
            $survey = Survey::query()->create([
                'id' => 'survey-filter-ajax',
                'title' => 'Filter AJAX Survey',
                'description' => 'Test',
                'isActive' => true,
            ]);
        }

        SurveyResponse::query()->create([
            'id' => 'resp-filter-ajax-1',
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'Ajax Filter Patient',
            'department' => 'Emergency',
            'overallScore' => 85,
            'submittedAt' => now(),
        ]);

        $this->actingAs($this->adminUser);

        $resp = $this->getJson(route('dashboard.responses.filter', ['q' => 'Ajax Filter']), [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resp->assertOk();
        $resp->assertJsonStructure([
            'html',
            'pagination',
            'total',
        ]);
        $resp->assertJsonMissingPath('error');
        $this->assertIsString($resp->json('html'));
        $this->assertIsInt($resp->json('total'));
        $this->assertStringContainsString('Ajax Filter Patient', $resp->json('html'));
    }

    public function test_admin_can_filter_tickets_via_ajax(): void
    {
        $survey = Survey::query()->first();
        if (! $survey) {
            $survey = Survey::query()->create([
                'id' => 'survey-ticket-filter',
                'title' => 'Ticket Filter Survey',
                'description' => 'Test',
                'isActive' => true,
            ]);
        }

        $surveyResponse = SurveyResponse::query()->create([
            'id' => 'resp-ticket-filter-1',
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'Ticket Filter Patient',
            'department' => 'Cardiology',
            'overallScore' => 75,
            'submittedAt' => now(),
        ]);

        Ticket::query()->create([
            'id' => 'ticket-filter-ajax-1',
            'responseId' => $surveyResponse->id,
            'department' => 'Cardiology',
            'patientName' => 'Ticket Filter Patient',
            'priority' => 'high',
            'status' => 'open',
            'description' => 'Test ticket for AJAX filter',
        ]);

        $this->actingAs($this->adminUser);

        $resp = $this->getJson(route('dashboard.tickets.filter', ['q' => 'Ticket Filter']), [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resp->assertOk();
        $resp->assertJsonStructure([
            'html',
            'pagination',
        ]);
        $resp->assertJsonMissingPath('error');
        $this->assertIsString($resp->json('html'));
        $this->assertStringContainsString('Ticket Filter Patient', $resp->json('html'));
    }

    public function test_head_of_department_cannot_see_responses_from_another_department_in_ajax_filter(): void
    {
        $hodUser = User::query()->create([
            'username' => 'hod_emergency_filter',
            'password' => bcrypt('password123'),
            'name' => 'HOD Emergency',
            'role' => 'head_of_department',
            'department' => 'Emergency',
            'isActive' => true,
        ]);

        $survey = Survey::query()->first();
        if (! $survey) {
            $survey = Survey::query()->create([
                'id' => 'survey-hod-filter',
                'title' => 'HOD Filter Survey',
                'description' => 'Test',
                'isActive' => true,
            ]);
        }

        SurveyResponse::query()->create([
            'id' => 'resp-hod-filter-emergency',
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'ZZZ_UNIQUE_Emergency_Patient',
            'department' => 'Emergency',
            'overallScore' => 90,
            'submittedAt' => now(),
        ]);

        SurveyResponse::query()->create([
            'id' => 'resp-hod-filter-pharmacy',
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'ZZZ_UNIQUE_Pharmacy_Patient',
            'department' => 'Pharmacy',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);

        $this->actingAs($hodUser);

        $resp = $this->getJson(route('dashboard.responses.filter', ['q' => 'ZZZ_UNIQUE']), [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resp->assertOk();
        $this->assertEquals(1, $resp->json('total'));
        $this->assertStringContainsString('Emergency', $resp->json('html'));
        $this->assertStringNotContainsString('Pharmacy', $resp->json('html'));
    }

    public function test_head_of_department_cannot_see_tickets_from_another_department_in_ajax_filter(): void
    {
        $hodUser = User::query()->create([
            'username' => 'hod_ticket_filter',
            'password' => bcrypt('password123'),
            'name' => 'HOD Ticket Filter',
            'role' => 'head_of_department',
            'department' => 'Emergency',
            'isActive' => true,
        ]);

        $survey = Survey::query()->first();
        if (! $survey) {
            $survey = Survey::query()->create([
                'id' => 'survey-hod-ticket-filter',
                'title' => 'HOD Ticket Filter Survey',
                'description' => 'Test',
                'isActive' => true,
            ]);
        }

        $emergencyResponse = SurveyResponse::query()->create([
            'id' => 'resp-hod-ticket-emergency',
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'Emergency Ticket Patient',
            'department' => 'Emergency',
            'overallScore' => 85,
            'submittedAt' => now(),
        ]);

        Ticket::query()->create([
            'id' => 'ticket-hod-filter-emergency',
            'responseId' => $emergencyResponse->id,
            'department' => 'Emergency',
            'patientName' => 'Emergency Ticket Patient',
            'priority' => 'high',
            'status' => 'open',
            'description' => 'Emergency department ticket',
        ]);

        $pharmacyResponse = SurveyResponse::query()->create([
            'id' => 'resp-hod-ticket-pharmacy',
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'Pharmacy Ticket Patient',
            'department' => 'Pharmacy',
            'overallScore' => 70,
            'submittedAt' => now(),
        ]);

        Ticket::query()->create([
            'id' => 'ticket-hod-filter-pharmacy',
            'responseId' => $pharmacyResponse->id,
            'department' => 'Pharmacy',
            'patientName' => 'Pharmacy Ticket Patient',
            'priority' => 'low',
            'status' => 'open',
            'description' => 'Pharmacy department ticket',
        ]);

        $this->actingAs($hodUser);

        $resp = $this->getJson(route('dashboard.tickets.filter'), [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resp->assertOk();
        $this->assertStringContainsString('Emergency', $resp->json('html'));
        $this->assertStringNotContainsString('Pharmacy', $resp->json('html'));
    }

    // ──────────────────────────────────────────────
    //  Role-based dashboard access tests
    // ──────────────────────────────────────────────

    public function test_super_admin_can_access_all_dashboard_pages(): void
    {
        $this->actingAs($this->adminUser);

        // General dashboard pages
        $this->get(route('dashboard.index'))->assertOk();
        $this->get(route('dashboard.responses'))->assertOk();
        $this->get(route('dashboard.tickets'))->assertOk();
        $this->get(route('dashboard.reports'))->assertOk();
        $this->get(route('dashboard.predictive'))->assertOk();
        $this->get(route('dashboard.hall-of-fame'))->assertOk();

        // Admin-only pages (web.role:super_admin,admin)
        $this->get(route('dashboard.surveys'))->assertOk();
        $this->get(route('dashboard.users'))->assertOk();
        $this->get(route('dashboard.settings'))->assertOk();
        $this->get(route('dashboard.audit'))->assertOk();
        $this->get(route('dashboard.monitoring'))->assertOk();
        $this->get(route('dashboard.error-logs'))->assertOk();
        $this->get(route('dashboard.backups'))->assertOk();
    }

    public function test_admin_can_access_all_dashboard_pages(): void
    {
        $admin = $this->createUserForRole('admin');
        $this->actingAs($admin);

        // General dashboard pages
        $this->get(route('dashboard.index'))->assertOk();
        $this->get(route('dashboard.responses'))->assertOk();
        $this->get(route('dashboard.tickets'))->assertOk();
        $this->get(route('dashboard.reports'))->assertOk();
        $this->get(route('dashboard.predictive'))->assertOk();
        $this->get(route('dashboard.hall-of-fame'))->assertOk();

        // Admin-only pages (web.role:super_admin,admin)
        $this->get(route('dashboard.surveys'))->assertOk();
        $this->get(route('dashboard.users'))->assertOk();
        $this->get(route('dashboard.settings'))->assertOk();
        $this->get(route('dashboard.audit'))->assertOk();
        $this->get(route('dashboard.monitoring'))->assertOk();
        $this->get(route('dashboard.error-logs'))->assertOk();
        $this->get(route('dashboard.backups'))->assertOk();
    }

    public function test_unit_manager_cannot_access_admin_only_pages(): void
    {
        $unitManager = $this->createUserForRole('unit_manager');
        $this->actingAs($unitManager);

        // Should be able to access general dashboard pages
        $this->get(route('dashboard.index'))->assertOk();
        $this->get(route('dashboard.responses'))->assertOk();
        $this->get(route('dashboard.tickets'))->assertOk();
        $this->get(route('dashboard.reports'))->assertOk();
        $this->get(route('dashboard.predictive'))->assertOk();
        $this->get(route('dashboard.hall-of-fame'))->assertOk();

        // Should be denied admin-only pages → 403 Forbidden (RequireWebRole middleware)
        $adminOnlyRoutes = [
            'dashboard.surveys',
            'dashboard.users',
            'dashboard.settings',
            'dashboard.audit',
            'dashboard.monitoring',
            'dashboard.error-logs',
            'dashboard.backups',
        ];
        foreach ($adminOnlyRoutes as $route) {
            $this->get(route($route))->assertStatus(403);
        }
    }

    public function test_head_of_department_cannot_access_admin_only_pages(): void
    {
        $hod = $this->createUserForRole('head_of_department', 'Emergency');
        $this->actingAs($hod);

        // Should be able to access general dashboard pages
        $this->get(route('dashboard.index'))->assertOk();
        $this->get(route('dashboard.responses'))->assertOk();
        $this->get(route('dashboard.tickets'))->assertOk();
        $this->get(route('dashboard.reports'))->assertOk();
        $this->get(route('dashboard.predictive'))->assertOk();
        $this->get(route('dashboard.hall-of-fame'))->assertOk();

        // Should be denied admin-only pages → 403 Forbidden (RequireWebRole middleware)
        $adminOnlyRoutes = [
            'dashboard.surveys',
            'dashboard.users',
            'dashboard.settings',
            'dashboard.audit',
            'dashboard.monitoring',
            'dashboard.error-logs',
            'dashboard.backups',
        ];
        foreach ($adminOnlyRoutes as $route) {
            $this->get(route($route))->assertStatus(403);
        }
    }

    public function test_staff_cannot_access_admin_only_pages(): void
    {
        $staff = $this->createUserForRole('staff');
        $this->actingAs($staff);

        // Should be able to access general dashboard pages
        // Note: reports, predictive, and hall-of-fame are NOT guarded by web.role middleware
        // in routes/web.php, so the backend currently allows staff to access them.
        $this->get(route('dashboard.index'))->assertOk();
        $this->get(route('dashboard.responses'))->assertOk();
        $this->get(route('dashboard.tickets'))->assertOk();
        $this->get(route('dashboard.reports'))->assertOk();
        $this->get(route('dashboard.predictive'))->assertOk();
        $this->get(route('dashboard.hall-of-fame'))->assertOk();

        // Should be denied admin-only pages → 403 Forbidden (RequireWebRole middleware)
        $adminOnlyRoutes = [
            'dashboard.surveys',
            'dashboard.users',
            'dashboard.settings',
            'dashboard.audit',
            'dashboard.monitoring',
            'dashboard.error-logs',
            'dashboard.backups',
        ];
        foreach ($adminOnlyRoutes as $route) {
            $this->get(route($route))->assertStatus(403);
        }
    }
}
