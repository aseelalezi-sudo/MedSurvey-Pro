<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use DatabaseTransactions;

    private SettingsService $settingsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settingsService = app(SettingsService::class);
    }

    public function test_defaults_returns_expected_structure(): void
    {
        $defaults = $this->settingsService->defaults();

        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('hospital', $defaults);
        $this->assertArrayHasKey('departments', $defaults);
        $this->assertArrayHasKey('ageGroups', $defaults);
        $this->assertArrayHasKey('visitTypes', $defaults);
        $this->assertArrayHasKey('surveySettings', $defaults);
        $this->assertArrayHasKey('appearance', $defaults);
        $this->assertArrayHasKey('backupSettings', $defaults);
    }

    public function test_get_public_returns_non_sensitive_settings(): void
    {
        $publicSettings = $this->settingsService->getPublic(null);

        $this->assertIsArray($publicSettings);
        $this->assertArrayHasKey('hospital', $publicSettings);
        $this->assertArrayHasKey('departments', $publicSettings);
        $this->assertArrayHasKey('ageGroups', $publicSettings);
        $this->assertArrayHasKey('visitTypes', $publicSettings);
        $this->assertArrayHasKey('surveySettings', $publicSettings);
        $this->assertArrayHasKey('appearance', $publicSettings);

        // Ensure sensitive backupSettings are NOT in public settings
        $this->assertArrayNotHasKey('backupSettings', $publicSettings);
    }

    public function test_update_saves_and_returns_settings(): void
    {
        $admin = User::query()->where('role', 'super_admin')->first();
        if (! $admin) {
            $admin = User::query()->create([
                'username' => 'test_super_admin',
                'password' => bcrypt('password123'),
                'name' => 'Test Super Admin',
                'role' => 'super_admin',
                'isActive' => true,
            ]);
        }

        $payload = [
            'hospital' => [
                'name' => 'Updated Hospital Name',
                'logo' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
            ],
            'appearance' => [
                'primaryColor' => '#FF0000',
            ],
        ];

        $updated = $this->settingsService->update($payload, $admin);

        $this->assertEquals('Updated Hospital Name', $updated['hospital']['name']);
        $this->assertEquals('#FF0000', $updated['appearance']['primaryColor']);

        // Check if retrieved settings match the updated values
        $allSettings = $this->settingsService->getAll($admin->tenantId);
        $this->assertEquals('Updated Hospital Name', $allSettings['hospital']['name']);
    }

    public function test_check_usage_returns_correct_stats(): void
    {
        $result = $this->settingsService->checkUsage('department', 'NonExistentDept12345', null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('inUse', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertFalse($result['inUse']);
        $this->assertEquals(0, $result['count']);
    }
}
