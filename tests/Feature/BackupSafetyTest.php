<?php

namespace Tests\Feature;

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
        $staff = $this->createUserForRole('staff');
        $this->actingAs($staff);
        $this->postJson(route('dashboard.backups.restore-external'), ['path' => 'test.sql'])->assertStatus(403);

        $this->actingAs($this->adminUser);
        $resp = $this->postJson(route('dashboard.backups.restore-external'), ['path' => 'test.sql']);
        $this->assertNotEquals(403, $resp->getStatusCode());
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

    public function test_create_backup_requires_mysqldump_binary(): void
    {
        $this->actingAs($this->adminUser);

        $resp = $this->post(route('dashboard.backups.create'));
        $resp->assertRedirect();
    }
}
