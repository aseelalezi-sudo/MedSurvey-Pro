<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SUPER_ADMIN_PASSWORD');
        if (app()->environment('production') && (! is_string($password) || strlen($password) < 12)) {
            throw new \RuntimeException('SUPER_ADMIN_PASSWORD must be set to a strong password in production.');
        }

        $this->call(RolesAndPermissionsSeeder::class);

        User::query()->updateOrCreate(
            ['username' => env('SUPER_ADMIN_USERNAME', 'admin')],
            [
                'password' => Hash::make($password ?: 'ChangeMeLocalOnly!123'),
                'name' => 'Super Admin',
                'email' => 'admin@medsurvey.local',
                'role' => 'super_admin',
                'isActive' => true,
            ],
        );

        // Seed rich demo data for manual testing (safe on migrate:fresh)
        if (! app()->environment('production')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
