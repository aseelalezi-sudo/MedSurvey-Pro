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
            ['method' => 'POST', 'name' => 'backups.restore', 'params' => ['filename' => 'test.sql']],
            ['method' => 'DELETE', 'name' => 'backups.destroy', 'params' => ['filename' => 'test.sql']],
            ['method' => 'POST', 'name' => 'backups.upload', 'params' => []],
            ['method' => 'POST', 'name' => 'backups.upload-restore', 'params' => []],
            ['method' => 'POST', 'name' => 'backups.scan-external', 'params' => []],
            ['method' => 'POST', 'name' => 'backups.verify-external', 'params' => []],
            ['method' => 'POST', 'name' => 'backups.restore-external', 'params' => []],
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

    public function test_verify_external_rejects_invalid_path_safely(): void
    {
        $this->actingAs($this->adminUser);

        $resp = $this->postJson(route('dashboard.backups.verify-external'), ['path' => '']);
        $resp->assertStatus(422);

        $resp = $this->postJson(route('dashboard.backups.verify-external'), ['path' => 'C:\\Windows\\system32']);
        $resp->assertStatus(422);

        $resp = $this->postJson(route('dashboard.backups.verify-external'), ['path' => '/nonexistent/path/file.sql']);
        $resp->assertStatus(422);

        $resp = $this->postJson(route('dashboard.backups.verify-external'), ['path' => '../../../etc/passwd']);
        $resp->assertStatus(422);
    }

    public function test_scan_external_rejects_path_outside_backup_dir(): void
    {
        $this->actingAs($this->adminUser);

        $resp = $this->postJson(route('dashboard.backups.scan-external'), ['path' => 'C:\\Windows']);
        $resp->assertStatus(422);
    }

    public function test_restore_external_is_guarded_by_admin_only_middleware(): void
    {
        config(['medsurvey.backup.restore_enabled' => true]);

        $staff = $this->createUserForRole('staff');
        $this->actingAs($staff);

        $this->postJson(route('dashboard.backups.restore-external'), ['path' => 'test.sql'])
            ->assertStatus(403);

        $this->actingAs($this->adminUser);

        $this->postJson(route('dashboard.backups.restore-external'), ['path' => 'test.sql'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_restore_routes_are_disabled_by_environment_setting(): void
    {
        config(['medsurvey.backup.restore_enabled' => false]);
        $this->actingAs($this->adminUser);

        $this->postJson(route('dashboard.backups.restore', ['filename' => 'test.sql']))
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->postJson(route('dashboard.backups.upload-restore'), [
            'filename' => 'test.sql',
            'content' => base64_encode('CREATE TABLE test (id int);'),
        ])
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->postJson(route('dashboard.backups.restore-external'), ['path' => 'test.sql'])
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_admin_cannot_restore_backup_even_when_restore_is_enabled(): void
    {
        config(['medsurvey.backup.restore_enabled' => true]);

        $admin = $this->createUserForRole('admin');
        $this->actingAs($admin);

        $response = $this->post(route('dashboard.backups.restore', [
            'filename' => 'backup.sql',
        ]));

        $response->assertForbidden();
    }

    public function test_admin_cannot_restore_external_backup_even_when_restore_is_enabled(): void
    {
        config(['medsurvey.backup.restore_enabled' => true]);

        $admin = $this->createUserForRole('admin');
        $this->actingAs($admin);

        $response = $this->postJson(route('dashboard.backups.restore-external'), [
            'path' => storage_path('app/backups/backup.sql'),
        ]);

        $response->assertForbidden();
    }

    public function test_backups_info_uses_configured_backup_directory_from_environment_fallback(): void
    {
        config(['medsurvey.backup.backup_dir' => 'storage/app/env-backups']);

        // Remove backupDir from settings to force config fallback
        Settings::query()->updateOrCreate(
            ['id' => 'global'],
            [
                'tenantId' => null,
                'data' => [
                    'backupSettings' => [
                        'schedule' => '03:00',
                        'retentionDays' => 30,
                        'compressGzip' => true,
                    ],
                ],
            ]
        );

        // Clear BackupService cached settings (previous tests may have set backupDir)
        app(BackupService::class)->clearCache();

        $this->actingAs($this->adminUser);

        $response = $this->get(route('dashboard.backups'));

        $response->assertOk();
        $response->assertSee('storage/app/env-backups');
    }

    public function test_create_backup_requires_mysqldump_binary(): void
    {
        $this->actingAs($this->adminUser);

        $resp = $this->post(route('dashboard.backups.create'));
        $resp->assertRedirect();
    }

    public function test_restore_rejects_dangerous_sql_patterns(): void
    {
        config(['medsurvey.backup.restore_enabled' => true]);
        $this->actingAs($this->adminUser);

        $dangerousPatterns = [
            'CREATE TABLE test (id int); SELECT * INTO OUTFILE "/tmp/test.txt"',
            'CREATE TABLE test (id int); SELECT LOAD_FILE("/etc/passwd")',
            'CREATE TABLE test (id int); system("rm -rf /")',
            'CREATE TABLE test (id int); \! sh',
            'CREATE TABLE test (id int); CREATE USER "hacker"@"%" IDENTIFIED BY "password"',
            'CREATE TABLE test (id int); GRANT ALL PRIVILEGES ON *.* TO "hacker"@"%"',
        ];

        foreach ($dangerousPatterns as $pattern) {
            $response = $this->postJson(route('dashboard.backups.upload-restore'), [
                'filename' => 'malicious_backup.sql',
                'content' => base64_encode($pattern),
            ]);

            $response->assertStatus(422)
                ->assertJsonPath('success', false)
                ->assertJsonFragment(['message' => 'Backup file contains potentially dangerous SQL statements and cannot be restored through the web interface.']);
        }
    }
}
