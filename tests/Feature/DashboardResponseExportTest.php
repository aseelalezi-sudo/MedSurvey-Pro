<?php

namespace Tests\Feature;

use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Concerns\CreatesTestData;
use Tests\TestCase;

class DashboardResponseExportTest extends TestCase
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

    public function test_dashboard_responses_page_supports_q_filtering(): void
    {
        $survey = $this->createTestSurvey();
        $prefix = 'ZZZ_FILTER_'.substr(bin2hex(random_bytes(4)), 0, 6);

        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Ali',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);
        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Sara',
            'department' => 'Emergency',
            'overallScore' => 90,
            'submittedAt' => now(),
        ]);

        $this->actingAs($this->adminUser);

        $resp = $this->get(route('dashboard.responses', ['q' => $prefix.'_Ali']));

        $resp->assertOk();
        $resp->assertSee($prefix.'_Ali');
        $resp->assertDontSee($prefix.'_Sara');
    }

    public function test_ajax_responses_filter_by_q_returns_exact_match(): void
    {
        $survey = $this->createTestSurvey();
        $prefix = 'ZZZ_AJAX_'.substr(bin2hex(random_bytes(4)), 0, 6);

        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Ali',
            'department' => 'Emergency',
            'overallScore' => 85,
            'submittedAt' => now(),
        ]);
        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Sara',
            'department' => 'Pharmacy',
            'overallScore' => 90,
            'submittedAt' => now(),
        ]);

        $this->actingAs($this->adminUser);

        $resp = $this->getJson(route('dashboard.responses.filter', ['q' => $prefix.'_Ali']), [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resp->assertOk();
        $resp->assertJsonStructure(['html', 'pagination', 'total']);
        $resp->assertJsonMissingPath('error');
        $this->assertEquals(1, $resp->json('total'));
        $this->assertStringContainsString($prefix.'_Ali', $resp->json('html'));
        $this->assertStringNotContainsString($prefix.'_Sara', $resp->json('html'));
    }

    public function test_score_filtering_works_for_all_categories(): void
    {
        $survey = $this->createTestSurvey();
        $prefix = 'ZZZ_SCORE_'.substr(bin2hex(random_bytes(4)), 0, 6);

        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Excellent',
            'department' => 'Emergency',
            'overallScore' => 90,
            'submittedAt' => now(),
        ]);
        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Good',
            'department' => 'Emergency',
            'overallScore' => 75,
            'submittedAt' => now(),
        ]);
        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Average',
            'department' => 'Emergency',
            'overallScore' => 60,
            'submittedAt' => now(),
        ]);
        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Poor',
            'department' => 'Emergency',
            'overallScore' => 30,
            'submittedAt' => now(),
        ]);

        $this->actingAs($this->adminUser);
        $headers = ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];

        $resp = $this->getJson(route('dashboard.responses.filter', ['q' => $prefix, 'score' => 'excellent']), $headers);
        $resp->assertOk();
        $this->assertStringContainsString($prefix.'_Excellent', $resp->json('html'));
        $this->assertStringNotContainsString($prefix.'_Good', $resp->json('html'));

        $resp = $this->getJson(route('dashboard.responses.filter', ['q' => $prefix, 'score' => 'good']), $headers);
        $resp->assertOk();
        $this->assertStringContainsString($prefix.'_Good', $resp->json('html'));

        $resp = $this->getJson(route('dashboard.responses.filter', ['q' => $prefix, 'score' => 'average']), $headers);
        $resp->assertOk();
        $this->assertStringContainsString($prefix.'_Average', $resp->json('html'));

        $resp = $this->getJson(route('dashboard.responses.filter', ['q' => $prefix, 'score' => 'poor']), $headers);
        $resp->assertOk();
        $this->assertStringContainsString($prefix.'_Poor', $resp->json('html'));
    }

    public function test_date_filtering_works(): void
    {
        $survey = $this->createTestSurvey();
        $prefix = 'ZZZ_DATE_'.substr(bin2hex(random_bytes(4)), 0, 6);

        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Today',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);
        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Old5',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now()->subDays(5),
        ]);
        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Old40',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now()->subDays(40),
        ]);

        $this->actingAs($this->adminUser);
        $headers = ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];

        $resp = $this->getJson(route('dashboard.responses.filter', ['q' => $prefix, 'dateFilter' => 'today']), $headers);
        $resp->assertOk();
        $this->assertStringContainsString($prefix.'_Today', $resp->json('html'));
        $this->assertStringNotContainsString($prefix.'_Old5', $resp->json('html'));

        $resp = $this->getJson(route('dashboard.responses.filter', ['q' => $prefix, 'dateFilter' => 'week']), $headers);
        $resp->assertOk();
        $this->assertStringContainsString($prefix.'_Today', $resp->json('html'));
        $this->assertStringContainsString($prefix.'_Old5', $resp->json('html'));
        $this->assertStringNotContainsString($prefix.'_Old40', $resp->json('html'));
    }

    public function test_custom_date_filter_caps_future_dates_at_today(): void
    {
        $survey = $this->createTestSurvey();
        $prefix = 'ZZZ_FUTURE_DATE_'.substr(bin2hex(random_bytes(4)), 0, 6);

        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Today',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);

        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Tomorrow',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now()->addDay(),
        ]);

        $this->actingAs($this->adminUser);

        $resp = $this->getJson(route('dashboard.responses.filter', [
            'q' => $prefix,
            'dateFilter' => 'custom',
            'startDate' => now()->subDay()->toDateString(),
            'endDate' => now()->addDay()->toDateString(),
        ]), ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']);

        $resp->assertOk();
        $this->assertStringContainsString($prefix.'_Today', $resp->json('html'));
        $this->assertStringNotContainsString($prefix.'_Tomorrow', $resp->json('html'));
    }

    public function test_department_filtering_works(): void
    {
        $survey = $this->createTestSurvey();
        $prefix = 'ZZZ_DEPT_'.substr(bin2hex(random_bytes(4)), 0, 6);

        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Emergency',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);
        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Pharmacy',
            'department' => 'Pharmacy',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);

        $this->actingAs($this->adminUser);
        $headers = ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];

        $resp = $this->getJson(route('dashboard.responses.filter', ['q' => $prefix, 'department' => 'Emergency']), $headers);
        $resp->assertOk();
        $this->assertStringContainsString($prefix.'_Emergency', $resp->json('html'));
        $this->assertStringNotContainsString($prefix.'_Pharmacy', $resp->json('html'));
    }

    public function test_gender_filtering_matches_exact_english_and_arabic_values(): void
    {
        $survey = $this->createTestSurvey();
        $prefix = 'ZZZ_GENDER_'.substr(bin2hex(random_bytes(4)), 0, 6);

        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_MaleEnglish',
            'gender' => 'male',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);
        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_MaleArabic',
            'gender' => 'ذكر',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);
        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_FemaleEnglish',
            'gender' => 'female',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);
        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_FemaleArabic',
            'gender' => 'أنثى',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);

        $this->actingAs($this->adminUser);
        $headers = ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];

        $maleResp = $this->getJson(route('dashboard.responses.filter', ['q' => $prefix, 'gender' => 'male']), $headers);
        $maleResp->assertOk();
        $this->assertSame(2, $maleResp->json('total'));
        $this->assertStringContainsString($prefix.'_MaleEnglish', $maleResp->json('html'));
        $this->assertStringContainsString($prefix.'_MaleArabic', $maleResp->json('html'));
        $this->assertStringNotContainsString($prefix.'_FemaleEnglish', $maleResp->json('html'));
        $this->assertStringNotContainsString($prefix.'_FemaleArabic', $maleResp->json('html'));

        $femaleResp = $this->getJson(route('dashboard.responses.filter', ['q' => $prefix, 'gender' => 'female']), $headers);
        $femaleResp->assertOk();
        $this->assertSame(2, $femaleResp->json('total'));
        $this->assertStringContainsString($prefix.'_FemaleEnglish', $femaleResp->json('html'));
        $this->assertStringContainsString($prefix.'_FemaleArabic', $femaleResp->json('html'));
        $this->assertStringNotContainsString($prefix.'_MaleEnglish', $femaleResp->json('html'));
        $this->assertStringNotContainsString($prefix.'_MaleArabic', $femaleResp->json('html'));
    }

    public function test_csv_export_returns_downloadable_response(): void
    {
        $survey = $this->createTestSurvey();
        $prefix = 'ZZZ_CSV_'.substr(bin2hex(random_bytes(4)), 0, 6);

        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Match',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);
        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Other',
            'department' => 'Pharmacy',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);

        $this->actingAs($this->adminUser);

        $resp = $this->get(route('dashboard.responses', ['export' => 'csv', 'q' => $prefix.'_Match']));

        $resp->assertOk();
        $resp->assertHeader('Content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=responses_export_', $resp->headers->get('Content-Disposition') ?? '');
        $content = $resp->streamedContent();
        $this->assertStringContainsString($prefix.'_Match', $content);
        $this->assertStringNotContainsString($prefix.'_Other', $content);
    }

    public function test_csv_export_respects_score_filter(): void
    {
        $survey = $this->createTestSurvey();
        $prefix = 'ZZZ_CSVFLT_'.substr(bin2hex(random_bytes(4)), 0, 6);

        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Good',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);
        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Poor',
            'department' => 'Emergency',
            'overallScore' => 30,
            'submittedAt' => now(),
        ]);

        $this->actingAs($this->adminUser);

        $resp = $this->get(route('dashboard.responses', ['export' => 'csv', 'q' => $prefix, 'score' => 'poor']));

        $resp->assertOk();
        $content = $resp->streamedContent();
        $this->assertStringContainsString($prefix.'_Poor', $content);
        $this->assertStringNotContainsString($prefix.'_Good', $content);
    }

    public function test_csv_export_respects_department_filter(): void
    {
        $survey = $this->createTestSurvey();
        $prefix = 'ZZZ_CSVDEPT_'.substr(bin2hex(random_bytes(4)), 0, 6);

        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Emergency',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);
        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Pharmacy',
            'department' => 'Pharmacy',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);

        $this->actingAs($this->adminUser);

        $resp = $this->get(route('dashboard.responses', ['export' => 'csv', 'q' => $prefix, 'department' => 'Emergency']));

        $resp->assertOk();
        $content = $resp->streamedContent();
        $this->assertStringContainsString($prefix.'_Emergency', $content);
        $this->assertStringNotContainsString($prefix.'_Pharmacy', $content);
    }

    public function test_print_export_returns_html_and_respects_filters(): void
    {
        $survey = $this->createTestSurvey();
        $prefix = 'ZZZ_PRINT_'.substr(bin2hex(random_bytes(4)), 0, 6);

        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Match',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);
        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Other',
            'department' => 'Pharmacy',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);

        $this->actingAs($this->adminUser);

        $resp = $this->get(route('dashboard.responses', ['export' => 'print', 'q' => $prefix.'_Match']));

        $resp->assertOk();
        $resp->assertSee($prefix.'_Match');
        $resp->assertDontSee($prefix.'_Other');
    }

    public function test_print_export_limits_large_result_sets(): void
    {
        $survey = $this->createTestSurvey();
        $prefix = 'ZZZ_PRINT_LIMIT_'.substr(bin2hex(random_bytes(4)), 0, 6);

        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Old_Excluded',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now()->subDays(10),
        ]);

        for ($i = 0; $i < 1000; $i++) {
            SurveyResponse::query()->create([
                'surveyId' => $survey->id,
                'answers' => ['q1' => 'a1'],
                'patientName' => $prefix.'_Included_'.$i,
                'department' => 'Emergency',
                'overallScore' => 80,
                'submittedAt' => now()->subMinutes($i),
            ]);
        }

        $this->actingAs($this->adminUser);

        $resp = $this->get(route('dashboard.responses', ['export' => 'print', 'q' => $prefix]));

        $resp->assertOk();
        $resp->assertSee($prefix.'_Included_0');
        $resp->assertDontSee($prefix.'_Old_Excluded');
    }

    public function test_hod_export_respects_department_scoping(): void
    {
        $hodUser = User::query()->create([
            'username' => 'hod_export_filter',
            'password' => bcrypt('password123'),
            'name' => 'HOD Export',
            'role' => 'head_of_department',
            'department' => 'Emergency',
            'isActive' => true,
        ]);

        $survey = $this->createTestSurvey();
        $prefix = 'ZZZ_HODEX_'.substr(bin2hex(random_bytes(4)), 0, 6);

        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Emergency',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);
        SurveyResponse::query()->create([
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => $prefix.'_Pharmacy',
            'department' => 'Pharmacy',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);

        $this->actingAs($hodUser);

        $csvResp = $this->get(route('dashboard.responses', ['export' => 'csv', 'q' => $prefix]));
        $csvResp->assertOk();
        $content = $csvResp->streamedContent();
        $this->assertStringContainsString($prefix.'_Emergency', $content);
        $this->assertStringNotContainsString($prefix.'_Pharmacy', $content);

        $printResp = $this->get(route('dashboard.responses', ['export' => 'print', 'q' => $prefix]));
        $printResp->assertOk();
        $printResp->assertSee($prefix.'_Emergency');
        $printResp->assertDontSee($prefix.'_Pharmacy');
    }
}
