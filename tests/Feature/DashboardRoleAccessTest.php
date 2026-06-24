<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Concerns\CreatesTestData;
use Tests\TestCase;

class DashboardRoleAccessTest extends TestCase
{
    use CreatesTestData;
    use DatabaseTransactions;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = $this->superAdminUser();
    }

    public function test_super_admin_can_access_all_dashboard_pages(): void
    {
        $this->actingAs($this->adminUser);

        $this->get(route('dashboard.index'))->assertOk();
        $this->get(route('dashboard.responses'))->assertOk();
        $this->get(route('dashboard.tickets'))->assertOk();
        $this->get(route('dashboard.reports'))->assertOk();
        $this->get(route('dashboard.predictive'))->assertOk();
        $this->get(route('dashboard.hall-of-fame'))->assertOk();

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

        $this->get(route('dashboard.index'))->assertOk();
        $this->get(route('dashboard.responses'))->assertOk();
        $this->get(route('dashboard.tickets'))->assertOk();
        $this->get(route('dashboard.reports'))->assertOk();
        $this->get(route('dashboard.predictive'))->assertOk();
        $this->get(route('dashboard.hall-of-fame'))->assertOk();

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

        $this->get(route('dashboard.index'))->assertOk();
        $this->get(route('dashboard.responses'))->assertOk();
        $this->get(route('dashboard.tickets'))->assertOk();
        $this->get(route('dashboard.reports'))->assertOk();
        $this->get(route('dashboard.predictive'))->assertOk();
        $this->get(route('dashboard.hall-of-fame'))->assertOk();

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

        $this->get(route('dashboard.index'))->assertOk();
        $this->get(route('dashboard.responses'))->assertOk();
        $this->get(route('dashboard.tickets'))->assertOk();
        $this->get(route('dashboard.reports'))->assertOk();
        $this->get(route('dashboard.predictive'))->assertOk();
        $this->get(route('dashboard.hall-of-fame'))->assertOk();

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

        $this->get(route('dashboard.index'))->assertRedirect(route('dashboard.responses'));
        $this->get(route('dashboard.responses'))->assertOk();

        $adminOnlyRoutes = [
            'dashboard.reports',
            'dashboard.predictive',
            'dashboard.hall-of-fame',
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

    public function test_unit_manager_cannot_update_ticket(): void
    {
        $unitManager = $this->createUserForRole('unit_manager');

        $survey = Survey::query()->create([
            'id' => 'test-survey-um',
            'title' => 'Test Survey',
            'description' => 'Test',
            'isActive' => true,
        ]);

        $surveyResponse = SurveyResponse::query()->create([
            'id' => 'test-response-um',
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'Test Patient',
            'department' => 'Emergency',
            'overallScore' => 30,
            'submittedAt' => now(),
        ]);

        $ticket = Ticket::query()->create([
            'id' => 'test-ticket-um',
            'responseId' => $surveyResponse->id,
            'department' => 'Emergency',
            'patientName' => 'Test Patient',
            'priority' => 'high',
            'status' => 'open',
            'description' => 'Test ticket',
        ]);

        $this->actingAs($unitManager);

        $response = $this->patch(route('dashboard.tickets.update', $ticket->id), [
            'status' => 'in_progress',
        ]);

        $response->assertStatus(403);

        $this->assertEquals('open', $ticket->fresh()->status);
    }

    public function test_admin_can_update_ticket(): void
    {
        $admin = $this->createUserForRole('admin');

        $survey = Survey::query()->create([
            'id' => 'test-survey-admin',
            'title' => 'Test Survey',
            'description' => 'Test',
            'isActive' => true,
        ]);

        $surveyResponse = SurveyResponse::query()->create([
            'id' => 'test-response-admin',
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'Test Patient',
            'department' => 'Emergency',
            'overallScore' => 30,
            'submittedAt' => now(),
        ]);

        $ticket = Ticket::query()->create([
            'id' => 'test-ticket-admin',
            'responseId' => $surveyResponse->id,
            'department' => 'Emergency',
            'patientName' => 'Test Patient',
            'priority' => 'high',
            'status' => 'open',
            'description' => 'Test ticket',
        ]);

        $this->actingAs($admin);

        $response = $this->patch(route('dashboard.tickets.update', $ticket->id), [
            'status' => 'in_progress',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'تم تحديث التذكرة بنجاح');

        $this->assertEquals('in_progress', $ticket->fresh()->status);
    }

    public function test_staff_only_sees_todays_responses(): void
    {
        $staff = $this->createUserForRole('staff');

        $survey = Survey::query()->create([
            'id' => 'test-survey-staff',
            'title' => 'Test Survey',
            'description' => 'Test',
            'isActive' => true,
        ]);

        // Response from today
        $todayResponse = SurveyResponse::query()->create([
            'id' => 'resp-today',
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'Today Patient',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now(),
        ]);

        // Response from yesterday
        $yesterdayResponse = SurveyResponse::query()->create([
            'id' => 'resp-yesterday',
            'surveyId' => $survey->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'Yesterday Patient',
            'department' => 'Emergency',
            'overallScore' => 80,
            'submittedAt' => now()->subDay(),
        ]);

        $this->actingAs($staff);

        // Access responses filter
        $response = $this->get(route('dashboard.responses.filter'));
        $response->assertOk();
        $response->assertSee('Today Patient');
        $response->assertDontSee('Yesterday Patient');
    }
}
