<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthSessionSecurityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_route_is_rate_limited(): void
    {
        User::query()->firstOrCreate(
            ['username' => 'rate_limited_user'],
            [
                'password' => bcrypt('correct-password'),
                'name' => 'Rate Limited User',
                'role' => 'staff',
                'isActive' => true,
            ]
        );

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post(route('login.store'), [
                'username' => 'rate_limited_user',
                'password' => 'wrong-password',
            ])->assertRedirect('/login');
        }

        $this->from('/login')->post(route('login.store'), [
            'username' => 'rate_limited_user',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }
}
