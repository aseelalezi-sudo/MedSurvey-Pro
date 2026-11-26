<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Concerns\CreatesTestData;
use Tests\TestCase;

class BackupSafetyTest extends TestCase
{
    use CreatesTestData;
    use DatabaseTransactions;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = $this->superAdminUser();
    }

    public function test_backups_require_a_system_administrator(): void
    {
        config(['medsurvey.backup.restore_enabled' => true]);
        $this->actingAs($this->adminUser);
        $this->get(route('dashboard.backups'))->assertOk();

        $admin = $this->createUserForRole('admin');
        $this->actingAs($admin);
        $this->get(route('dashboard.backups'))->assertForbidden();

        $nonAdminRoles = ['unit_manager', 'staff'];
        foreach ($nonAdminRoles as $role) {
            $user = $this->createUserForRole($role);
            $this->actingAs($user);
            $this->get(route('dashboard.backups'))->assertStatus(403);
        }

        $hod = $this->createUserForRole('head_of_department', 'Emergency');
        $this->actingAs($hod);
        $this->get(route('dashboard.backups'))->assertStatus(403);
    }

    public function test_backups_info_box_uses_configured_backup_directory(): void
    {
        $this->actingAs($this->adminUser);

        Settings::query()->updateOrCreate(
            ['id' => 'global'],
            [
                'tenantId' => null,
                'data' => [
                    'backupSettings' => [
                        'schedule' => '04:30',
                        'retentionDays' => 12,
                        'compressGzip' => true,
                        'backupDir' => 'storage/app/custom-backups',
                    ],
                ],
            ]
        );

        $this->get(route('dashboard.backups'))
            ->assertOk()
            ->assertSee('storage/app/custom-backups')
            ->assertDontSee('D:\\MedSurvey Pro\\storage/app/backups');
    }

    public function test_tenant_accounts_cannot_manage_system_backups_even_with_direct_permissions(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Backup Tenant']);
        foreach (['admin', 'super_admin'] as $role) {
            $user = $this->userWithRole($role, ['tenantId' => $tenant->id]);
            $user->givePermissionTo(['operations.backups.view', 'operations.backups.create', 'operations.backups.download', 'operations.backups.delete']);
            $this->actingAs($user);
            $this->get(route('dashboard.backups'))->assertForbidden();
            $this->post(route('dashboard.backups.create'))->assertForbidden();
            $this->post(route('dashboard.backups.verify', 'sample.sql'))->assertForbidden();
            $this->get(route('dashboard.backups.download', 'sample.sql'))->assertForbidden();
            $this->delete(route('dashboard.backups.destroy', 'sample.sql'))->assertForbidden();
        }
    }

    public function test_non_admin_cannot_access_backup_mutation_routes(): void
    {
        $nonAdminRoles = ['unit_manager', 'head_of_department', 'staff'];

        $mutationRoutes = [
            ['method' => 'POST', 'name' => 'backups.create', 'params' => []],
            ['method' => 'POST', 'name' => 'backups.verify', 'params' => ['filename' => 'test.sql']],
            ['method' => 'DELETE', 'name' => 'backups.destroy', 'params' => ['filename' => 'test.sql']],
        ];

        foreach ($nonAdminRoles as $role) {
            $user = $this->createUserForRole($role, $role === 'head_of_department' ? 'Emergency' : null);

            foreach ($mutationRoutes as $route) {
                $this->actingAs($user);

                if ($route['method'] === 'POST') {
                    $this->post(route("dashboard.{$route['name']}", $route['params']))->assertStatus(403);
                } elseif ($route['method'] === 'DELETE') {
                    $this->delete(route("dashboard.{$route['name']}", $route['params']))->assertStatus(403);
                }
            }
        }
    }

    public function test_download_backup_rejects_dangerous_filenames(): void
    {
        $this->actingAs($this->adminUser);

        $dangerousFilenames = [
            '../.env',
            '../../database.sqlite',
            'C:\\Windows\\system32',
            'backup.sql; rm -rf /',
            '../../.env',
        ];

        foreach ($dangerousFilenames as $filename) {
            $resp = $this->get(route('dashboard.backups.download', ['filename' => $filename]));
            $resp->assertRedirect();
        }
    }

    public function test_verify_backup_rejects_nonexistent_filename(): void
    {
        $this->actingAs($this->adminUser);

        $resp = $this->post(route('dashboard.backups.verify', ['filename' => 'nonexistent_backup.sql']));
        $resp->assertRedirect();
    }

    public function test_destroy_backup_rejects_nonexistent_filename(): void
    {
        $this->actingAs($this->adminUser);

        $resp = $this->delete(route('dashboard.backups.destroy', ['filename' => 'nonexistent_backup.sql']));
        $resp->assertRedirect();
    }
}
