<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
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

    public function test_backups_page_is_admin_only(): void
    {
        config(['medsurvey.backup.restore_enabled' => true]);
        $this->actingAs($this->adminUser);
        $this->get(route('dashboard.backups'))->assertOk();

        $admin = $this->createUserForRole('admin');
        $this->actingAs($admin);
        $this->get(route('dashboard.backups'))->assertOk();

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

    public function test_backups_table_uses_configured_backup_directory_for_current_tenant(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Backup Tenant',
        ]);
        $admin = User::query()->create([
            'username' => 'tenant_backup_admin_'.bin2hex(random_bytes(4)),
            'password' => bcrypt('password123'),
            'name' => 'Tenant Backup Admin',
            'role' => 'admin',
            'isActive' => true,
            'tenantId' => $tenant->id,
        ]);
        $backupDir = 'custom-test-backups-'.bin2hex(random_bytes(4));
        $absoluteDir = base_path($backupDir);
        File::ensureDirectoryExists($absoluteDir);
        File::put($absoluteDir.DIRECTORY_SEPARATOR.'tenant_custom_backup.sql', 'CREATE TABLE tenant_backup_test (id int);');

        Settings::query()->create([
            'tenantId' => $tenant->id,
            'data' => [
                'backupSettings' => [
                    'schedule' => '05:15',
                    'retentionDays' => 9,
                    'compressGzip' => false,
                    'backupDir' => $backupDir,
                ],
            ],
        ]);

        try {
            $this->actingAs($admin)
                ->get(route('dashboard.backups'))
                ->assertOk()
                ->assertSee('tenant_custom_backup.sql')
                ->assertSee($backupDir);
        } finally {
            File::deleteDirectory($absoluteDir);
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
