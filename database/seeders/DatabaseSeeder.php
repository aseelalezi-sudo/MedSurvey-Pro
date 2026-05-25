<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['username' => env('SUPER_ADMIN_USERNAME', 'admin')],
            [
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'admin12345')),
                'name' => 'Super Admin',
                'email' => '',
                'role' => 'super_admin',
                'isActive' => true,
            ],
        );
    }
}

