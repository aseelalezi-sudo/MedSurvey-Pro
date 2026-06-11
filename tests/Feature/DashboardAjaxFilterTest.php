<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\Concerns\CreatesTestData;
use Tests\TestCase;

class DashboardAjaxFilterTest extends TestCase
{
    use CreatesTestData;
    use DatabaseTransactions;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = $this->superAdminUser();
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

    public function test_hall_of_fame_uses_confidence_weighted_ranking(): void
    {
        Cache::flush();

        $this->actingAs($this->adminUser);

        $survey = Survey::query()->firstOrCreate(
            ['id' => 'survey-hall-confidence-ranking'],
            [
                'title' => 'Hall Confidence Ranking Survey',
                'description' => 'Test',
                'isActive' => true,
            ],
        );

        $prefix = 'Hall Confidence '.substr(bin2hex(random_bytes(4)), 0, 6);
        $singlePerfectDepartment = $prefix.' Single Perfect';
        $establishedDepartment = $prefix.' Established Excellent';

        SurveyResponse::query()->create([
            'id' => 'resp-hall-confidence-single-'.substr(bin2hex(random_bytes(4)), 0, 6),
            'surveyId' => $survey->id,
            'answers' => ['overall' => 100],
            'department' => $singlePerfectDepartment,
            'overallScore' => 100,
            'submittedAt' => now(),
        ]);

        for ($i = 0; $i < 12; $i++) {
            SurveyResponse::query()->create([
                'id' => 'resp-hall-confidence-established-'.$i.'-'.substr(bin2hex(random_bytes(4)), 0, 6),
                'surveyId' => $survey->id,
                'answers' => ['overall' => 95],
                'department' => $establishedDepartment,
                'overallScore' => 95,
                'submittedAt' => now(),
            ]);
        }

        $resp = $this->get(route('dashboard.hall-of-fame', ['q' => $prefix]));

        $resp->assertOk();
        $resp->assertSee($establishedDepartment);
        $resp->assertSee($singlePerfectDepartment);
        $leaderboardTable = substr($resp->getContent(), strrpos($resp->getContent(), '<tbody'));

        $this->assertLessThan(
            strpos($leaderboardTable, $singlePerfectDepartment),
            strpos($leaderboardTable, $establishedDepartment),
        );
    }

    public function test_dashboard_honor_board_uses_confidence_weighted_ranking(): void
    {
        Cache::flush();

        $this->actingAs($this->adminUser);

        $survey = Survey::query()->firstOrCreate(
            ['id' => 'survey-dashboard-hall-confidence-ranking'],
            [
                'title' => 'Dashboard Hall Confidence Ranking Survey',
                'description' => 'Test',
                'isActive' => true,
            ],
        );

        $prefix = 'Dashboard Hall Confidence '.substr(bin2hex(random_bytes(4)), 0, 6);
        $singlePerfectDepartment = $prefix.' Single Perfect';
        $establishedDepartment = $prefix.' Established Excellent';

        SurveyResponse::query()->create([
            'id' => 'resp-dashboard-hall-confidence-single-'.substr(bin2hex(random_bytes(4)), 0, 6),
            'surveyId' => $survey->id,
            'answers' => ['overall' => 100],
            'department' => $singlePerfectDepartment,
            'overallScore' => 100,
            'submittedAt' => now(),
        ]);

        for ($i = 0; $i < 12; $i++) {
            SurveyResponse::query()->create([
                'id' => 'resp-dashboard-hall-confidence-established-'.$i.'-'.substr(bin2hex(random_bytes(4)), 0, 6),
                'surveyId' => $survey->id,
                'answers' => ['overall' => 95],
                'department' => $establishedDepartment,
                'overallScore' => 95,
                'submittedAt' => now(),
            ]);
        }

        $resp = $this->get(route('dashboard.index'));

        $resp->assertOk();
        $resp->assertSee($establishedDepartment);
        $resp->assertSee($singlePerfectDepartment);

        $content = $resp->getContent();
        $honorBoardSection = substr($content, strpos($content, __('honor_board')));

        $this->assertLessThan(
            strpos($honorBoardSection, $singlePerfectDepartment),
            strpos($honorBoardSection, $establishedDepartment),
        );
    }

    public function test_hall_of_fame_search_preserves_global_rank_numbers(): void
    {
        Cache::flush();

        $this->actingAs($this->adminUser);

        $survey = Survey::query()->firstOrCreate(
            ['id' => 'survey-hall-search-rank'],
            [
                'title' => 'Hall Search Rank Survey',
                'description' => 'Test',
                'isActive' => true,
            ],
        );

        $prefix = 'Hall Search Rank '.substr(bin2hex(random_bytes(4)), 0, 6);
        $leaderDepartment = $prefix.' Global Leader';
        $filteredDepartment = $prefix.' Filtered Runner Up';

        for ($i = 0; $i < 20; $i++) {
            SurveyResponse::query()->create([
                'id' => 'resp-hall-search-rank-leader-'.$i.'-'.substr(bin2hex(random_bytes(4)), 0, 6),
                'surveyId' => $survey->id,
                'answers' => ['overall' => 100],
                'department' => $leaderDepartment,
                'overallScore' => 100,
                'submittedAt' => now(),
            ]);

            SurveyResponse::query()->create([
                'id' => 'resp-hall-search-rank-filtered-'.$i.'-'.substr(bin2hex(random_bytes(4)), 0, 6),
                'surveyId' => $survey->id,
                'answers' => ['overall' => 95],
                'department' => $filteredDepartment,
                'overallScore' => 95,
                'submittedAt' => now(),
            ]);
        }

        $resp = $this->get(route('dashboard.hall-of-fame', ['q' => $filteredDepartment]));

        $resp->assertOk();
        $resp->assertDontSee($leaderDepartment);
        $resp->assertSee($filteredDepartment);

        $leaderboardTable = substr($resp->getContent(), strrpos($resp->getContent(), '<tbody'));

        $this->assertMatchesRegularExpression(
            '/<span[^>]*>\s*[2-9]\d*\s*<\/span>.*'.preg_quote($filteredDepartment, '/').'/s',
            $leaderboardTable,
        );
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

    public function test_reports_monthly_trend_returns_aggregated_month_buckets(): void
    {
        $survey = Survey::query()->first();
        if (! $survey) {
            $survey = Survey::query()->create([
                'id' => 'survey-monthly-trend',
                'title' => 'Monthly Trend Survey',
                'description' => 'Test',
                'isActive' => true,
            ]);
        }

        $section = SurveySection::query()->create([
            'surveyId' => $survey->id,
            'title' => 'Monthly Trend NPS',
            'description' => 'NPS section description',
            'icon' => 'clipboard-check',
            'sortOrder' => 1,
        ]);

        $npsQuestion = SurveyQuestion::query()->create([
            'sectionId' => $section->id,
            'type' => 'nps',
            'title' => 'Would you recommend us?',
            'description' => null,
            'required' => false,
            'category' => 'NPS',
            'options' => null,
            'followUp' => null,
            'sortOrder' => 1,
        ]);

        $department = 'Monthly Trend Department '.substr(bin2hex(random_bytes(4)), 0, 6);

        $currentOne = SurveyResponse::query()->create([
            'id' => 'resp-monthly-trend-current-1-'.substr(bin2hex(random_bytes(4)), 0, 6),
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'Monthly Trend Current One',
            'department' => $department,
            'overallScore' => 100,
            'submittedAt' => now()->startOfMonth()->addDays(2),
        ]);

        $currentTwo = SurveyResponse::query()->create([
            'id' => 'resp-monthly-trend-current-2-'.substr(bin2hex(random_bytes(4)), 0, 6),
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'Monthly Trend Current Two',
            'department' => $department,
            'overallScore' => 60,
            'submittedAt' => now()->startOfMonth()->addDays(3),
        ]);

        $previous = SurveyResponse::query()->create([
            'id' => 'resp-monthly-trend-previous-'.substr(bin2hex(random_bytes(4)), 0, 6),
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'Monthly Trend Previous',
            'department' => $department,
            'overallScore' => 75,
            'submittedAt' => now()->subMonth()->startOfMonth()->addDays(2),
        ]);

        SurveyAnswer::query()->create([
            'id' => 'answer-monthly-trend-current-1-'.substr(bin2hex(random_bytes(4)), 0, 6),
            'responseId' => $currentOne->id,
            'questionId' => $npsQuestion->id,
            'value' => '10',
        ]);

        SurveyAnswer::query()->create([
            'id' => 'answer-monthly-trend-current-2-'.substr(bin2hex(random_bytes(4)), 0, 6),
            'responseId' => $currentTwo->id,
            'questionId' => $npsQuestion->id,
            'value' => '9',
        ]);

        SurveyAnswer::query()->create([
            'id' => 'answer-monthly-trend-previous-'.substr(bin2hex(random_bytes(4)), 0, 6),
            'responseId' => $previous->id,
            'questionId' => $npsQuestion->id,
            'value' => '5',
        ]);

        $this->actingAs($this->adminUser);

        $resp = $this->getJson(route('dashboard.reports', [
            'department' => $department,
        ]), [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resp->assertOk();

        $trendData = $resp->json('trendData');

        $this->assertCount(6, $trendData);

        $currentMonth = collect($trendData)->firstWhere('month', now()->format('Y-m'));
        $previousMonth = collect($trendData)->firstWhere('month', now()->subMonth()->format('Y-m'));

        $this->assertNotNull($currentMonth);
        $this->assertNotNull($previousMonth);

        $this->assertSame(2, $currentMonth['totalResponses']);
        $this->assertSame(80.0, (float) $currentMonth['averageScore']);
        $this->assertSame(1, $currentMonth['excellent']);
        $this->assertSame(0, $currentMonth['good']);
        $this->assertSame(1, $currentMonth['average']);
        $this->assertSame(0, $currentMonth['poor']);

        $this->assertSame(1, $previousMonth['totalResponses']);
        $this->assertSame(75.0, (float) $previousMonth['averageScore']);
        $this->assertSame(0, $previousMonth['excellent']);
        $this->assertSame(1, $previousMonth['good']);

        $this->assertSame(100.0, (float) $currentMonth['npsScore']);
        $this->assertSame(-100.0, (float) $previousMonth['npsScore']);
    }

    public function test_reports_department_trends_use_current_and_previous_period_buckets(): void
    {
        $survey = Survey::query()->first();
        if (! $survey) {
            $survey = Survey::query()->create([
                'id' => 'survey-department-trend',
                'title' => 'Department Trend Survey',
                'description' => 'Test',
                'isActive' => true,
            ]);
        }

        $department = 'Department Trend '.substr(bin2hex(random_bytes(4)), 0, 6);

        SurveyResponse::query()->create([
            'id' => 'resp-dept-trend-current-1-'.substr(bin2hex(random_bytes(4)), 0, 6),
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'Department Trend Current One',
            'department' => $department,
            'overallScore' => 90,
            'submittedAt' => now()->subDays(3),
        ]);

        SurveyResponse::query()->create([
            'id' => 'resp-dept-trend-current-2-'.substr(bin2hex(random_bytes(4)), 0, 6),
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'Department Trend Current Two',
            'department' => $department,
            'overallScore' => 70,
            'submittedAt' => now()->subDays(2),
        ]);

        SurveyResponse::query()->create([
            'id' => 'resp-dept-trend-previous-1-'.substr(bin2hex(random_bytes(4)), 0, 6),
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'Department Trend Previous One',
            'department' => $department,
            'overallScore' => 50,
            'submittedAt' => now()->subDays(10),
        ]);

        SurveyResponse::query()->create([
            'id' => 'resp-dept-trend-previous-2-'.substr(bin2hex(random_bytes(4)), 0, 6),
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'Department Trend Previous Two',
            'department' => $department,
            'overallScore' => 60,
            'submittedAt' => now()->subDays(9),
        ]);

        $this->actingAs($this->adminUser);

        $resp = $this->getJson(route('dashboard.reports', [
            'department' => $department,
            'dateFilter' => 'week',
        ]), [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resp->assertOk();

        $deptTrend = collect($resp->json('deptTrends'))->firstWhere('name', $department);

        $this->assertNotNull($deptTrend);
        $this->assertSame(80, $deptTrend['currentScore']);
        $this->assertSame(2, $deptTrend['currentCount']);
        $this->assertSame(55, $deptTrend['previousScore']);
        $this->assertSame(2, $deptTrend['previousCount']);
        $this->assertSame(25.0, (float) $deptTrend['change']);
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
