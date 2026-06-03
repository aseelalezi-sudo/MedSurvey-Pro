<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\E2ePredictiveSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class E2eSeederCredentialsTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        $this->unsetEnvironmentVariable('TEST_ADMIN_USERNAME');
        $this->unsetEnvironmentVariable('TEST_ADMIN_PASSWORD');

        parent::tearDown();
    }

    public function test_e2e_predictive_seeder_uses_test_admin_environment_credentials(): void
    {
        $username = 'ci_e2e_admin';
        $password = 'StrongE2ePassword123!';

        $this->setEnvironmentVariable('TEST_ADMIN_USERNAME', $username);
        $this->setEnvironmentVariable('TEST_ADMIN_PASSWORD', $password);

        $this->seed(E2ePredictiveSeeder::class);

        $user = User::query()->where('username', $username)->first();

        $this->assertNotNull($user);
        $this->assertSame('super_admin', $user->role);
        $this->assertSame('Super Admin', $user->name);
        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function test_e2e_predictive_seeder_keeps_default_credentials_when_environment_is_missing(): void
    {
        $this->unsetEnvironmentVariable('TEST_ADMIN_USERNAME');
        $this->unsetEnvironmentVariable('TEST_ADMIN_PASSWORD');

        $this->seed(E2ePredictiveSeeder::class);

        $user = User::query()->where('username', 'super_admin')->first();

        $this->assertNotNull($user);
        $this->assertSame('super_admin', $user->role);
        $this->assertTrue(Hash::check('Password123!', $user->password));
    }

    private function setEnvironmentVariable(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function unsetEnvironmentVariable(string $key): void
    {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}
