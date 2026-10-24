<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\Feature\Concerns\CreatesTestData;
use Tests\TestCase;

class TenantManagementTest extends TestCase
{
    use CreatesTestData;
    use DatabaseTransactions;

    private User $globalAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->globalAdmin = $this->userWithRole('super_admin', ['tenantId' => null]);
    }

    private function tenant(?string $name = null): Tenant
    {
        return Tenant::query()->create([
            'name' => $name ?? 'Tenant '.bin2hex(random_bytes(4)),
            'isActive' => true,
        ]);
    }

    public function test_global_super_admin_can_create_rename_and_delete_an_empty_tenant(): void
    {
        $this->actingAs($this->globalAdmin);

        $this->get(route('dashboard.tenants'))->assertOk()->assertSee('Tenant Management', false);

        $name = 'North Clinic '.bin2hex(random_bytes(3));
        $this->post(route('dashboard.tenants.store'), ['name' => $name])
            ->assertRedirect(route('dashboard.tenants'))
            ->assertSessionHas('success');

        $tenant = Tenant::query()->where('name', $name)->firstOrFail();
        $this->assertTrue($tenant->isActive);

        $this->put(route('dashboard.tenants.update', $tenant->id), ['name' => $name.' 2'])
            ->assertRedirect(route('dashboard.tenants'));
        $this->assertSame($name.' 2', $tenant->fresh()->name);

        $this->delete(route('dashboard.tenants.destroy', $tenant->id))
            ->assertRedirect(route('dashboard.tenants'))
            ->assertSessionHas('success');
        $this->assertNull(Tenant::query()->find($tenant->id));
    }

    public function test_tenant_names_must_be_unique(): void
    {
        $existing = $this->tenant();
        $this->actingAs($this->globalAdmin);

        $this->post(route('dashboard.tenants.store'), ['name' => $existing->name])
            ->assertSessionHasErrors('name');
    }

    public function test_only_a_tenant_less_super_admin_can_manage_tenants(): void
    {
        $tenant = $this->tenant();
        $boundSuperAdmin = $this->userWithRole('super_admin', ['tenantId' => $tenant->id]);
        $admin = $this->userWithRole('admin');

        foreach ([$boundSuperAdmin, $admin] as $user) {
            $this->actingAs($user);

            $this->get(route('dashboard.tenants'))->assertForbidden();
            $this->post(route('dashboard.tenants.store'), ['name' => 'Rogue'])->assertForbidden();
            $this->put(route('dashboard.tenants.update', $tenant->id), ['name' => 'Rogue'])->assertForbidden();
            $this->patch(route('dashboard.tenants.toggle', $tenant->id))->assertForbidden();
            $this->delete(route('dashboard.tenants.destroy', $tenant->id))->assertForbidden();
        }

        $this->assertNotNull(Tenant::query()->find($tenant->id));
        $this->assertTrue($tenant->fresh()->isActive);
        $this->assertNull(Tenant::query()->where('name', 'Rogue')->first());
    }

    public function test_a_tenant_with_data_cannot_be_deleted(): void
    {
        $tenant = $this->tenant();
        $this->userWithRole('staff', ['tenantId' => $tenant->id]);
        $this->actingAs($this->globalAdmin);

        $this->delete(route('dashboard.tenants.destroy', $tenant->id))
            ->assertRedirect(route('dashboard.tenants'))
            ->assertSessionHas('error');

        $this->assertNotNull(Tenant::query()->find($tenant->id));
    }

    public function test_a_tenant_that_owns_only_a_survey_cannot_be_deleted(): void
    {
        $tenant = $this->tenant();
        Survey::query()->create([
            'title' => 'Owned survey',
            'description' => 'x',
            'isActive' => true,
            'tenantId' => $tenant->id,
        ]);
        $this->actingAs($this->globalAdmin);

        $this->delete(route('dashboard.tenants.destroy', $tenant->id))->assertSessionHas('error');

        $this->assertNotNull(Tenant::query()->find($tenant->id));
    }

    public function test_an_administrator_can_be_created_for_a_tenant(): void
    {
        $tenant = $this->tenant();
        $this->actingAs($this->globalAdmin);

        $username = 'tenant_admin_'.bin2hex(random_bytes(3));
        $this->post(route('dashboard.tenants.admins.store', $tenant->id), [
            'username' => $username,
            'name' => 'Facility Admin',
            'password' => 'Secret123!',
        ])->assertRedirect(route('dashboard.tenants'))->assertSessionHas('success');

        $admin = User::query()->where('username', $username)->firstOrFail();
        $this->assertSame($tenant->id, $admin->tenantId);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->isActive);
    }

    public function test_suspending_a_tenant_blocks_its_users_everywhere(): void
    {
        $tenant = $this->tenant();
        $staff = $this->userWithRole('staff', ['tenantId' => $tenant->id]);
        $staff->createToken('phone', ['app']);

        // Active tenant: dashboard and API login work.
        $this->actingAs($staff)->get(route('dashboard.responses'))->assertOk();
        $this->postJson('/api/v1/auth/login', ['username' => $staff->username, 'password' => 'Password123!'])
            ->assertOk();

        $this->actingAs($this->globalAdmin)
            ->patch(route('dashboard.tenants.toggle', $tenant->id))
            ->assertRedirect(route('dashboard.tenants'));

        $this->assertFalse($tenant->fresh()->isActive);
        $this->assertSame(0, $staff->tokens()->count(), 'app tokens are revoked immediately');

        // The existing web session ends on its next request. (A real request
        // loads the user afresh; reload it here so the test does too.)
        $staff = $staff->fresh();
        $this->actingAs($staff)->get(route('dashboard.responses'))->assertRedirect(route('login'));
        $this->assertGuest();

        // New logins are refused, with the same error as wrong credentials.
        $this->postJson('/api/v1/auth/login', ['username' => $staff->username, 'password' => 'Password123!'])
            ->assertUnprocessable();
        $this->post(route('login.store'), ['username' => $staff->username, 'password' => 'Password123!'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();

        // Reactivating restores access.
        $this->actingAs($this->globalAdmin)->patch(route('dashboard.tenants.toggle', $tenant->id));
        $this->assertTrue($tenant->fresh()->isActive);
        $this->actingAs($staff->fresh())->get(route('dashboard.responses'))->assertOk();
    }

    public function test_tenant_actions_are_written_to_the_audit_log(): void
    {
        $this->actingAs($this->globalAdmin);
        $name = 'Audited '.bin2hex(random_bytes(3));

        $this->post(route('dashboard.tenants.store'), ['name' => $name])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'userId' => $this->globalAdmin->id,
            'action' => 'create_tenant',
        ]);
    }
}
