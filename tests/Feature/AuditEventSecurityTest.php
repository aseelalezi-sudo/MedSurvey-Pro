<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuditEventSecurityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_audit_event_rejects_unknown_client_action(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(route('dashboard.audit.events'), [
            'action' => 'delete_all_audit_logs',
            'messageKey' => 'audit.details.delete_all_audit_logs',
            'params' => ['reportType' => 'overview'],
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseMissing('audit_logs', [
            'userId' => $admin->id,
            'action' => 'delete_all_audit_logs',
        ]);
    }

    public function test_audit_event_accepts_report_actions(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(route('dashboard.audit.events'), [
            'action' => 'print_report',
            'messageKey' => 'audit.details.print_report',
            'params' => [
                'reportType' => 'overview',
                'department' => 'all',
                'dateRange' => '30d',
            ],
        ]);

        $response->assertOk()->assertJson(['status' => 'success']);
        $this->assertDatabaseHas('audit_logs', [
            'userId' => $admin->id,
            'action' => 'print_report',
        ]);
    }

    private function adminUser(): User
    {
        return User::query()->where('role', 'super_admin')->first()
            ?? User::query()->create([
                'username' => 'audit_event_admin',
                'password' => bcrypt('password123'),
                'name' => 'Audit Event Admin',
                'role' => 'super_admin',
                'isActive' => true,
            ]);
    }
}
