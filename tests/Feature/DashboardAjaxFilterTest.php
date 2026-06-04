<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DashboardAjaxFilterTest extends TestCase
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

    public function test_ticket_date_filter_caps_future_dates_at_today(): void
    {
        $survey = Survey::query()->first();
        if (! $survey) {
            $survey = Survey::query()->create([
                'id' => 'survey-ticket-future-filter',
                'title' => 'Ticket Future Filter Survey',
                'description' => 'Test',
                'isActive' => true,
            ]);
        }

        $prefix = 'Ticket Future Filter '.substr(bin2hex(random_bytes(4)), 0, 6);

        $todayResponse = SurveyResponse::query()->create([
            'id' => 'resp-ticket-future-today-'.substr(bin2hex(random_bytes(4)), 0, 6),
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.' Today',
            'department' => 'Cardiology',
            'overallScore' => 75,
            'submittedAt' => now(),
        ]);

        $futureResponse = SurveyResponse::query()->create([
            'id' => 'resp-ticket-future-tomorrow-'.substr(bin2hex(random_bytes(4)), 0, 6),
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.' Tomorrow',
            'department' => 'Cardiology',
            'overallScore' => 75,
            'submittedAt' => now()->addDay(),
        ]);

        Ticket::query()->create([
            'id' => 'ticket-future-today-'.substr(bin2hex(random_bytes(4)), 0, 6),
            'responseId' => $todayResponse->id,
            'department' => 'Cardiology',
            'patientName' => $prefix.' Today',
            'priority' => 'high',
            'status' => 'open',
            'description' => 'Today ticket',
        ]);

        $futureTicket = Ticket::query()->create([
            'id' => 'ticket-future-tomorrow-'.substr(bin2hex(random_bytes(4)), 0, 6),
            'responseId' => $futureResponse->id,
            'department' => 'Cardiology',
            'patientName' => $prefix.' Tomorrow',
            'priority' => 'high',
            'status' => 'open',
            'description' => 'Tomorrow ticket',
        ]);
        $futureTicket->createdAt = now()->addDay();
        $futureTicket->save();

        $this->actingAs($this->adminUser);

        $resp = $this->getJson(route('dashboard.tickets.filter', [
            'q' => $prefix,
            'dateFilter' => 'custom',
            'startDate' => now()->subDay()->toDateString(),
            'endDate' => now()->addDay()->toDateString(),
        ]), ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']);

        $resp->assertOk();
        $this->assertStringContainsString($prefix.' Today', $resp->json('html'));
        $this->assertStringNotContainsString($prefix.' Tomorrow', $resp->json('html'));
    }

    public function test_reports_filters_return_json_for_ajax_requests(): void
    {
        $this->actingAs($this->adminUser);

        $resp = $this->getJson(route('dashboard.reports', [
            'dateFilter' => 'week',
            'department' => 'all',
        ]), [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resp->assertOk();
        $resp->assertJsonStructure([
            'stats',
            'comparisonStats',
            'trendData',
            'deptTrends',
            'tickets',
            'changes',
        ]);
    }

    public function test_hall_of_fame_page_exposes_replaceable_content_region(): void
    {
        $this->actingAs($this->adminUser);

        $resp = $this->get(route('dashboard.hall-of-fame', [
            'dateFilter' => 'week',
        ]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resp->assertOk();
        $resp->assertSee('hall-of-fame-content', false);
    }

    public function test_surveys_page_exposes_replaceable_content_and_json_state(): void
    {
        $this->actingAs($this->adminUser);

        $resp = $this->get(route('dashboard.surveys'), [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resp->assertOk();
        $resp->assertSee('surveys-content', false);
        $resp->assertSee('surveys-json', false);
    }

    public function test_survey_toggle_returns_json_for_ajax_requests(): void
    {
        $survey = Survey::query()->first();
        if (! $survey) {
            $survey = Survey::query()->create([
                'id' => 'survey-toggle-json',
                'title' => 'Toggle JSON Survey',
                'description' => 'Test',
                'isActive' => true,
            ]);
        }

        $this->actingAs($this->adminUser);

        $resp = $this->patchJson(route('dashboard.surveys.toggle', $survey->id), [], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resp->assertOk();
        $resp->assertJsonPath('success', true);
        $resp->assertJsonStructure(['success', 'survey']);
    }

    public function test_users_page_exposes_replaceable_content_region(): void
    {
        $this->actingAs($this->adminUser);

        $resp = $this->get(route('dashboard.users'), [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resp->assertOk();
        $resp->assertSee('users-content', false);
    }

    public function test_user_toggle_returns_json_for_ajax_requests(): void
    {
        $user = User::query()->create([
            'username' => 'ajax_toggle_user_'.substr(bin2hex(random_bytes(4)), 0, 6),
            'password' => bcrypt('password123'),
            'name' => 'AJAX Toggle User',
            'email' => 'ajax-toggle@example.com',
            'role' => 'staff',
            'isActive' => true,
        ]);

        $this->actingAs($this->adminUser);

        $resp = $this->patchJson(route('dashboard.users.toggle', $user->id), [], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resp->assertOk();
        $resp->assertJsonPath('success', true);
        $resp->assertJsonStructure(['success', 'user']);
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
}
