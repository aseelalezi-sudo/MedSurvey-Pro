<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OperationsControllerSecurityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tenant_admins_can_only_see_their_own_tenant_logs(): void
    {
        Tenant::query()->firstOrCreate(['id' => 'tenant-A'], ['name' => 'Tenant A']);
        Tenant::query()->firstOrCreate(['id' => 'tenant-B'], ['name' => 'Tenant B']);

        $adminA = User::query()->create([
            'username' => 'admin_a_audit',
            'password' => bcrypt('password123'),
            'name' => 'Admin A',
            'role' => 'admin',
            'isActive' => true,
            'tenantId' => 'tenant-A',
        ]);

        $adminB = User::query()->create([
            'username' => 'admin_b_audit',
            'password' => bcrypt('password123'),
            'name' => 'Admin B',
            'role' => 'admin',
            'isActive' => true,
            'tenantId' => 'tenant-B',
        ]);

        AuditLog::query()->create([
            'id' => 90001,
            'userId' => $adminA->id,
            'tenantId' => 'tenant-A',
            'action' => 'test_action_a',
            'details' => '{}',
            'timestamp' => now(),
        ]);

        AuditLog::query()->create([
            'id' => 90002,
            'userId' => $adminB->id,
            'tenantId' => 'tenant-B',
            'action' => 'test_action_b',
            'details' => '{}',
            'timestamp' => now(),
        ]);

        $this->actingAs($adminA);

        $response = $this->getJson(route('dashboard.audit', ['ajax' => 'true']), [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('test_action_a', $content);
        $this->assertStringNotContainsString('test_action_b', $content);
    }
}
