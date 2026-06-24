<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define Permissions
        $permissions = [
            // Dashboard
            'dashboard.view',
            
            // Surveys
            'surveys.view',
            'surveys.create',
            'surveys.update',
            'surveys.delete',
            'surveys.duplicate',
            'surveys.toggle-status',

            // Responses
            'responses.view',
            'responses.view-contact',
            'responses.export',
            'responses.print',

            // Tickets
            'tickets.view',
            'tickets.update',
            'tickets.delete',
            'tickets.change-status',
            'tickets.add-note',
            'tickets.assign',

            // Reports & Analytics
            'reports.view',
            'predictive.view',
            'predictive.manage',
            'hall-of-fame.view',

            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.manage-roles',
            'users.manage-permissions',

            // Settings
            'settings.view',
            'settings.update',

            // Operations & Logs
            'operations.audit-logs.view',
            'operations.monitoring.view',
            'operations.error-logs.view',
            'operations.error-logs.delete',
            'operations.backups.view',
            'operations.backups.create',
            'operations.backups.delete',
            'operations.backups.download',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // 2. Define Roles and assign permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions([
            'dashboard.view',
            'surveys.view', 'surveys.create', 'surveys.update', 'surveys.delete', 'surveys.duplicate', 'surveys.toggle-status',
            'responses.view', 'responses.view-contact', 'responses.export', 'responses.print',
            'tickets.view', 'tickets.update', 'tickets.delete', 'tickets.change-status', 'tickets.add-note', 'tickets.assign',
            'reports.view', 'predictive.view', 'predictive.manage', 'hall-of-fame.view',
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.manage-roles', 'users.manage-permissions',
            'settings.view', 'settings.update',
            'operations.audit-logs.view', 'operations.monitoring.view', 'operations.error-logs.view', 'operations.error-logs.delete',
            'operations.backups.view', 'operations.backups.create', 'operations.backups.delete', 'operations.backups.download',
        ]);

        $headOfDepartment = Role::firstOrCreate(['name' => 'head_of_department', 'guard_name' => 'web']);
        $headOfDepartment->syncPermissions([
            'dashboard.view',
            'reports.view', 'predictive.view', 'hall-of-fame.view',
            'tickets.view', 'tickets.update', 'tickets.change-status', 'tickets.add-note',
            'responses.view', 'responses.export', 'responses.print',
        ]);

        $unitManager = Role::firstOrCreate(['name' => 'unit_manager', 'guard_name' => 'web']);
        $unitManager->syncPermissions([
            'dashboard.view',
            'reports.view', 'predictive.view', 'hall-of-fame.view',
            'tickets.view',
            'responses.view', 'responses.export', 'responses.print',
        ]);

        $staff = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $staff->syncPermissions([
            'dashboard.view',
            'responses.view',
            'tickets.view',
        ]);

        // 3. Migrate Existing Users safely
        $roleMap = [
            'super_admin' => 'super_admin',
            'admin' => 'admin',
            'head_of_department' => 'head_of_department',
            'unit_manager' => 'unit_manager',
            'staff' => 'staff',
        ];

        User::chunk(100, function ($users) use ($roleMap) {
            foreach ($users as $user) {
                // Check if user's role exists in our mapping
                $roleName = $roleMap[$user->role] ?? null;
                if ($roleName) {
                    $user->assignRole($roleName);
                }
            }
        });
    }
}
