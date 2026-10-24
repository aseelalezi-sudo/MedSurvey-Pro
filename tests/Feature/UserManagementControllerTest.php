<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_super_admin_can_create_update_toggle_and_delete_user(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $username = 'managed_user_'.bin2hex(random_bytes(4));

        $createResponse = $this->actingAs($superAdmin)->post(route('dashboard.users.store'), [
            'username' => $username,
            'password' => 'Password123!',
            'name' => 'Managed User',
            'email' => 'managed@example.test',
            'role' => 'staff',
            'isActive' => true,
        ]);

        $createResponse->assertRedirect();
        $user = User::query()->where('username', $username)->firstOrFail();
        $this->assertSame('staff', $user->role);
        $this->assertTrue(Hash::check('Password123!', $user->password));

        $updateResponse = $this->actingAs($superAdmin)->put(route('dashboard.users.update', $user), [
            'username' => $username.'_updated',
            'password' => 'NewPassword123!',
            'name' => 'Updated Managed User',
            'email' => 'updated@example.test',
            'role' => 'head_of_department',
            'department' => 'Emergency',
            'isActive' => true,
        ]);

        $updateResponse->assertRedirect();
        $user->refresh();
        $this->assertSame($username.'_updated', $user->username);
        $this->assertSame('head_of_department', $user->role);
        $this->assertSame('Emergency', $user->department);
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));

        $toggleResponse = $this->actingAs($superAdmin)->patch(route('dashboard.users.toggle', $user));
        $toggleResponse->assertRedirect();
        $this->assertFalse($user->fresh()->isActive);

        $deleteResponse = $this->actingAs($superAdmin)->delete(route('dashboard.users.destroy', $user));
        $deleteResponse->assertRedirect();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_create_super_admin_or_modify_existing_super_admin(): void
    {
        $admin = $this->userWithRole('admin');
        $superAdmin = $this->userWithRole('super_admin');

        $createResponse = $this->actingAs($admin)->post(route('dashboard.users.store'), [
            'username' => 'blocked_super_'.bin2hex(random_bytes(4)),
            'password' => 'Password123!',
            'name' => 'Blocked Super Admin',
            'email' => 'blocked@example.test',
            'role' => 'super_admin',
            'isActive' => true,
        ]);

        $createResponse->assertRedirect();
        $createResponse->assertSessionHas('error');

        $updateResponse = $this->actingAs($admin)->put(route('dashboard.users.update', $superAdmin), [
            'username' => $superAdmin->username,
            'name' => 'Should Not Change',
            'email' => 'blocked-update@example.test',
            'role' => 'admin',
            'isActive' => true,
        ]);

        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHas('error');
        $this->assertNotSame('Should Not Change', $superAdmin->fresh()->name);
    }

    public function test_user_cannot_toggle_or_delete_own_account(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $toggleResponse = $this->actingAs($superAdmin)->patch(route('dashboard.users.toggle', $superAdmin));
        $toggleResponse->assertRedirect();
        $toggleResponse->assertSessionHas('error');
        $this->assertTrue($superAdmin->fresh()->isActive);

        $deleteResponse = $this->actingAs($superAdmin)->delete(route('dashboard.users.destroy', $superAdmin));
        $deleteResponse->assertRedirect();
        $deleteResponse->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
    }

    private function userWithRole(string $role): User
    {
        return User::query()->create([
            'username' => $role.'_manager_'.bin2hex(random_bytes(4)),
            'password' => bcrypt('Password123!'),
            'name' => ucfirst(str_replace('_', ' ', $role)).' User',
            'email' => $role.'_'.bin2hex(random_bytes(4)).'@example.test',
            'role' => $role,
            'isActive' => true,
        ]);
    }

    public function test_changing_deactivating_or_deleting_a_user_revokes_their_app_tokens(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $user = $this->userWithRole('staff');
        $tokenCount = fn () => $user->tokens()->count();

        $user->createToken('phone', ['app']);
        $this->assertSame(1, $tokenCount());

        // Editing without touching password, role or department keeps the session.
        $this->actingAs($superAdmin)->put(route('dashboard.users.update', $user), [
            'username' => $user->username,
            'name' => 'Renamed Collector',
            'email' => 'renamed@example.test',
            'role' => $user->role,
            'isActive' => true,
        ])->assertRedirect();
        $this->assertSame(1, $tokenCount());

        // A new password ends the app session.
        $this->actingAs($superAdmin)->put(route('dashboard.users.update', $user), [
            'username' => $user->username,
            'password' => 'NewPassword123!',
            'name' => 'Renamed Collector',
            'email' => 'renamed@example.test',
            'role' => $user->role,
            'isActive' => true,
        ])->assertRedirect();
        $this->assertSame(0, $tokenCount());

        // So does a role change.
        $user->createToken('phone', ['app']);
        $this->actingAs($superAdmin)->put(route('dashboard.users.update', $user), [
            'username' => $user->username,
            'name' => 'Renamed Collector',
            'email' => 'renamed@example.test',
            'role' => 'unit_manager',
            'isActive' => true,
        ])->assertRedirect();
        $this->assertSame(0, $tokenCount());

        // Deactivation, so reactivating later does not revive an old token.
        $user->createToken('phone', ['app']);
        $this->actingAs($superAdmin)->patch(route('dashboard.users.toggle', $user))->assertRedirect();
        $this->assertFalse($user->fresh()->isActive);
        $this->assertSame(0, $tokenCount());

        $user->createToken('phone', ['app']);
        $this->actingAs($superAdmin)->delete(route('dashboard.users.destroy', $user))->assertRedirect();
        $this->assertSame(0, $tokenCount());
    }

    public function test_changing_your_own_password_revokes_app_tokens(): void
    {
        $user = $this->userWithRole('staff');
        $user->createToken('phone', ['app']);

        $this->actingAs($user)->post(route('dashboard.change-password'), [
            'currentPassword' => 'Password123!',
            'password' => 'BrandNew123!',
            'password_confirmation' => 'BrandNew123!',
        ])->assertRedirect();

        $this->assertSame(0, $user->tokens()->count());
    }
}
