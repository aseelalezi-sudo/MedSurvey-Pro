<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['username' => env('SUPER_ADMIN_USERNAME', 'admin')],
            [
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'admin123')),
                'name' => 'Super Admin',
                'email' => 'admin@medsurvey.local',
                'role' => 'super_admin',
                'isActive' => true,
            ],
        );
    }
}