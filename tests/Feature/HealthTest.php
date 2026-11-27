<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $this->get('/up')->assertOk();

        $response = $this->getJson('/api/health');

        $response->assertOk()->assertJsonStructure([
            'status',
            'timestamp',
            'service',
        ]);
    }

    public function test_readiness_reports_dependency_failure_without_database_details(): void
    {
        $connection = config('database.default');
        config(['database.default' => 'private-unavailable-connection']);
        try {
            $this->getJson('/api/health')->assertServiceUnavailable()
                ->assertJsonPath('status', 'unavailable')->assertDontSee('private-unavailable-connection');
        } finally {
            config(['database.default' => $connection]);
        }
    }
}
