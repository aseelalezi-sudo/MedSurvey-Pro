<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DashboardTenantScopingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reports_page_scopes_tickets_by_tenant_without_ticket_tenant_column(): void
    {
        Tenant::query()->create(['id' => 'tenant-a', 'name' => 'Tenant A']);
        Tenant::query()->create(['id' => 'tenant-b', 'name' => 'Tenant B']);

        $user = User::query()->create([
            'username' => 'tenant_a_report_user',
            'password' => bcrypt('password123'),
            'name' => 'Tenant A Report User',
            'role' => 'admin',
            'tenantId' => 'tenant-a',
            'isActive' => true,
        ]);

        $surveyA = Survey::query()->create([
            'id' => 'tenant-a-survey',
            'title' => 'Tenant A Survey',
            'description' => 'Test',
            'isActive' => true,
            'tenantId' => 'tenant-a',
        ]);

        $surveyB = Survey::query()->create([
            'id' => 'tenant-b-survey',
            'title' => 'Tenant B Survey',
            'description' => 'Test',
            'isActive' => true,
            'tenantId' => 'tenant-b',
        ]);

        $responseA = SurveyResponse::query()->create([
            'id' => 'tenant-a-response',
            'surveyId' => $surveyA->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'Tenant A Ticket Patient',
            'department' => 'Emergency',
            'overallScore' => 30,
            'submittedAt' => now(),
            'tenantId' => 'tenant-a',
        ]);

        $responseB = SurveyResponse::query()->create([
            'id' => 'tenant-b-response',
            'surveyId' => $surveyB->id,
            'answers' => ['q1' => 'a1'],
            'patientName' => 'Tenant B Ticket Patient',
            'department' => 'Pharmacy',
            'overallScore' => 30,
            'submittedAt' => now(),
            'tenantId' => 'tenant-b',
        ]);

        Ticket::query()->create([
            'id' => 'tenant-a-ticket',
            'responseId' => $responseA->id,
            'department' => 'Emergency',
            'patientName' => 'Tenant A Ticket Patient',
            'priority' => 'high',
            'status' => 'open',
            'description' => 'Tenant A scoped ticket',
        ]);

        Ticket::query()->create([
            'id' => 'tenant-b-ticket',
            'responseId' => $responseB->id,
            'department' => 'Pharmacy',
            'patientName' => 'Tenant B Ticket Patient',
            'priority' => 'high',
            'status' => 'open',
            'description' => 'Tenant B hidden ticket',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('dashboard.reports'));

        $response->assertOk();
        $response->assertSee('Tenant A Ticket Patient');
        $response->assertDontSee('Tenant B Ticket Patient');
    }
}
