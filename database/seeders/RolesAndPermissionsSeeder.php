<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define Permissions
        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Patients
            'patients.view',
            'patients.create',
            'patients.update',
            'patients.delete',
            'patients.view-phone',

            // Reports
            'reports.view',
            'reports.export',

            // Tickets
            'tickets.view',
            'tickets.create',
            'tickets.assign',
            'tickets.update',
            'tickets.delete',

            // Responses
            'responses.view',
            'responses.create',
            'responses.delete',

            // Settings
            'settings.manage',

            // Operations (Logs, Backups)
            'operations.manage',
            'operations.manage-backups',
            'operations.manage-error-logs',
            'operations.manage-audit-logs',
            
            // Surveys
            'surveys.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 2. Define Roles and assign permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        // Super admin gets all permissions via Gate::before in AppServiceProvider, but let's sync all just in case
        $superAdmin->syncPermissions(Permission::all());

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions([
            'users.view',
            'users.create',
            'users.update', // But cannot update super_admin (handled in Controller/Policy)
            
            'patients.view',
            'patients.create',
            'patients.update',
            'patients.delete',
            'patients.view-phone',
            
            'reports.view',
            'reports.export',
            
            'tickets.view',
            'tickets.create',
            'tickets.assign',
            'tickets.update',
            'tickets.delete',
            
            'responses.view',
            'responses.create',
            
            'settings.manage',
            
            'operations.manage-backups',
            
            'surveys.manage',
        ]);

        $headOfDepartment = Role::firstOrCreate(['name' => 'head_of_department']);
        $headOfDepartment->syncPermissions([
            'patients.view',
            
            'reports.view', // Only for their department (handled in policy/query)
            'reports.export',
            
            'tickets.view', // Only their department
            'tickets.update', // Resolve
            
            'responses.view', // Only their department
        ]);

        $unitManager = Role::firstOrCreate(['name' => 'unit_manager']);
        $unitManager->syncPermissions([
            'patients.view',
            'reports.view',
            'reports.export',
            'tickets.view',
        ]);

        $staff = Role::firstOrCreate(['name' => 'staff']);
        $staff->syncPermissions([
            'patients.view',
            'responses.view', // Only today's responses for staff
            'tickets.view', // If applicable
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
