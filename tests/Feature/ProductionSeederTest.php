<?php

namespace Tests\Feature;

use Database\Seeders\DemoDataSeeder;
use Database\Seeders\E2ePredictiveSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    public static function demoSeeders(): array
    {
        return [[DemoDataSeeder::class], [E2ePredictiveSeeder::class]];
    }

    #[DataProvider('demoSeeders')]
    public function test_production_rejects_demo_seeders_before_writing_data(string $seeder): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->app->make($seeder)->run();
            $this->fail('Expected production to reject the demo seeder.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('disabled in production', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }
}
