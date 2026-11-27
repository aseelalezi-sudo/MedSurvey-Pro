<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ErrorLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Concerns\CreatesTestData;
use Tests\TestCase;

class OperationsControllerSecurityTest extends TestCase
{
    use CreatesTestData;
    use DatabaseTransactions;

    public function test_facility_bound_super_admin_does_not_gain_global_log_scope(): void
    {
        $a = Tenant::create(['name' => 'Clinic A']);
        $b = Tenant::create(['name' => 'Clinic B']);
        $user = $this->userWithRole('super_admin', ['tenantId' => $a->id]);
        $foreign = AuditLog::create(['userId' => $user->id, 'tenantId' => $b->id, 'action' => 'example', 'details' => '{}', 'timestamp' => now()]);
        $this->assertFalse(AuditLog::visibleTo($user)->whereKey($foreign->id)->exists());
        $this->assertFalse($user->can('manage-super-admin-users'));
    }

    public function test_database_errors_keep_bindings_out_of_operational_logs(): void
    {
        $error = new QueryException('mysql', 'select ?', ['private-fixture-value'], new \PDOException('example', 23000));
        report($error);
        $log = ErrorLog::query()->where('metadata->class', $error::class)->firstOrFail();
        $this->assertStringContainsString('23000', $log->message);
        $this->assertStringNotContainsString('private-fixture-value', $log->toJson());
    }

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
