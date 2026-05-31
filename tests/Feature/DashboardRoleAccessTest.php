<?php

namespace Tests\Feature;

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
}
