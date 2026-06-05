<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsSecurityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_settings_rejects_unsupported_hospital_logo_data_urls(): void
    {
        $admin = User::query()->where('role', 'super_admin')->first();
        if (! $admin) {
            $admin = User::query()->create([
                'username' => 'settings_security_admin',
                'password' => bcrypt('password123'),
                'name' => 'Settings Security Admin',
                'role' => 'super_admin',
                'isActive' => true,
            ]);
        }

        $this->actingAs($admin);

        $response = $this->from(route('dashboard.settings'))->put(route('dashboard.settings.update'), [
            'hospital' => [
                'name' => 'Security Test Hospital',
                'logo' => 'data:image/svg+xml;base64,'.base64_encode('<svg onload="alert(1)"></svg>'),
            ],
        ]);

        $response->assertRedirect(route('dashboard.settings'));
        $response->assertSessionHasErrors('hospital.logo');
    }

    public function test_settings_accepts_supported_hospital_logo_data_urls(): void
    {
        Storage::fake('public');

        $admin = User::query()->where('role', 'super_admin')->first();
        if (! $admin) {
            $admin = User::query()->create([
                'username' => 'settings_security_admin_valid',
                'password' => bcrypt('password123'),
                'name' => 'Settings Security Admin Valid',
                'role' => 'super_admin',
                'isActive' => true,
            ]);
        }

        $this->actingAs($admin);

        $response = $this->from(route('dashboard.settings'))->put(route('dashboard.settings.update'), [
            'hospital' => [
                'name' => 'Security Test Hospital',
                'logo' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
            ],
        ]);

        $response->assertRedirect(route('dashboard.settings'));
        $response->assertSessionHasNoErrors();

        $settings = Settings::query()
            ->where('tenantId', $admin->tenantId)
            ->when($admin->tenantId === null, fn ($query) => $query->where('id', 'global'))
            ->firstOrFail();
        $storedLogo = $settings->data['hospital']['logo'] ?? '';

        $this->assertStringStartsWith('/storage/settings/logos/', $storedLogo);
        $this->assertStringEndsWith('.png', $storedLogo);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $storedLogo));
        $this->assertLessThan(250, strlen($storedLogo));
    }

    public function test_settings_can_save_empty_managed_lists(): void
    {
        $admin = $this->superAdminUser();
        $this->actingAs($admin);

        Settings::query()->updateOrCreate(
            ['id' => 'global'],
            [
                'tenantId' => null,
                'data' => [
                    'departments' => [
                        ['id' => 'dept-test', 'name' => 'Test Department', 'isActive' => true, 'color' => '#0d9488'],
                    ],
                    'ageGroups' => [
                        ['id' => 'age-test', 'label' => 'Test Age', 'isActive' => true],
                    ],
                    'visitTypes' => [
                        ['id' => 'visit-test', 'label' => 'Test Visit', 'isActive' => true],
                    ],
                ],
            ]
        );

        $response = $this->from(route('dashboard.settings'))->put(route('dashboard.settings.update'), [
            'departments_present' => '1',
            'ageGroups_present' => '1',
            'visitTypes_present' => '1',
        ]);

        $response->assertRedirect(route('dashboard.settings'));

        $settings = Settings::query()->where('id', 'global')->firstOrFail();
        $this->assertSame([], $settings->data['departments'] ?? null);
        $this->assertSame([], $settings->data['ageGroups'] ?? null);
        $this->assertSame([], $settings->data['visitTypes'] ?? null);
    }

    public function test_settings_saves_hidden_survey_requirement_values(): void
    {
        $admin = $this->superAdminUser();
        $this->actingAs($admin);

        Settings::query()->updateOrCreate(
            ['id' => 'global'],
            [
                'tenantId' => null,
                'data' => [
                    'surveySettings' => [
                        'allowAnonymous' => false,
                        'requireName' => true,
                        'requirePhone' => true,
                    ],
                ],
            ]
        );

        $response = $this->from(route('dashboard.settings'))->put(route('dashboard.settings.update'), [
            'surveySettings' => [
                'allowAnonymous' => '1',
                'requireName' => '0',
                'requirePhone' => '0',
            ],
        ]);

        $response->assertRedirect(route('dashboard.settings'));

        $settings = Settings::query()->where('id', 'global')->firstOrFail();
        $this->assertFalse((bool) ($settings->data['surveySettings']['requireName'] ?? true));
        $this->assertFalse((bool) ($settings->data['surveySettings']['requirePhone'] ?? true));
    }

    public function test_settings_saves_entered_hospital_email(): void
    {
        $admin = $this->superAdminUser();
        $this->actingAs($admin);

        $response = $this->from(route('dashboard.settings'))->put(route('dashboard.settings.update'), [
            'hospital' => [
                'name' => 'Email Test Hospital',
                'email' => 'contact@example.test',
            ],
        ]);

        $response->assertRedirect(route('dashboard.settings'));
        $response->assertSessionHasNoErrors();

        $settings = Settings::query()->where('id', 'global')->firstOrFail();
        $this->assertSame('contact@example.test', $settings->data['hospital']['email'] ?? null);
    }

    public function test_settings_saves_plain_hospital_website_text(): void
    {
        $admin = $this->superAdminUser();
        $this->actingAs($admin);

        $response = $this->from(route('dashboard.settings'))->put(route('dashboard.settings.update'), [
            'hospital' => [
                'name' => 'Website Test Hospital',
                'email' => 'contact@example.test',
                'website' => 'hospital.local',
            ],
        ]);

        $response->assertRedirect(route('dashboard.settings'));
        $response->assertSessionHasNoErrors();

        $settings = Settings::query()->where('id', 'global')->firstOrFail();
        $this->assertSame('hospital.local', $settings->data['hospital']['website'] ?? null);
    }

    public function test_settings_saves_managed_list_active_flags_as_booleans(): void
    {
        $admin = $this->superAdminUser();
        $this->actingAs($admin);

        $response = $this->from(route('dashboard.settings'))->put(route('dashboard.settings.update'), [
            'departments_present' => '1',
            'ageGroups_present' => '1',
            'visitTypes_present' => '1',
            'departments' => [
                ['id' => 'dept-active-flag', 'name' => 'Department Flag', 'isActive' => '0', 'color' => '#0d9488'],
            ],
            'ageGroups' => [
                ['id' => 'age-active-flag', 'label' => 'Age Flag', 'isActive' => '0'],
            ],
            'visitTypes' => [
                ['id' => 'visit-active-flag', 'label' => 'Visit Flag', 'isActive' => '1'],
            ],
        ]);

        $response->assertRedirect(route('dashboard.settings'));
        $response->assertSessionHasNoErrors();

        $settings = Settings::query()->where('id', 'global')->firstOrFail();
        $this->assertFalse($settings->data['departments'][0]['isActive']);
        $this->assertFalse($settings->data['ageGroups'][0]['isActive']);
        $this->assertTrue($settings->data['visitTypes'][0]['isActive']);
    }

    public function test_settings_update_returns_json_for_ajax_requests(): void
    {
        $admin = $this->superAdminUser();
        $this->actingAs($admin);

        $response = $this->putJson(route('dashboard.settings.update'), [
            'hospital' => [
                'name' => 'Ajax Settings Hospital',
                'email' => 'ajax-settings@example.test',
            ],
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $settings = Settings::query()->where('id', 'global')->firstOrFail();
        $this->assertSame('Ajax Settings Hospital', $settings->data['hospital']['name'] ?? null);
    }

    public function test_appearance_setting_can_hide_dashboard_language_toggle(): void
    {
        $admin = $this->superAdminUser();
        $this->actingAs($admin);

        Settings::query()->updateOrCreate(
            ['id' => 'global'],
            [
                'tenantId' => null,
                'data' => [
                    'appearance' => [
                        'showLanguageToggle' => false,
                    ],
                ],
            ]
        );

        $this->get(route('dashboard.settings'))
            ->assertOk()
            ->assertDontSee('set-locale');
    }

    public function test_settings_ajax_can_disable_language_toggle(): void
    {
        $admin = $this->superAdminUser();
        $this->actingAs($admin);

        $response = $this->putJson(route('dashboard.settings.update'), [
            'appearance' => [
                'showLanguageToggle' => '0',
            ],
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $settings = Settings::query()->where('id', 'global')->firstOrFail();
        $this->assertFalse($settings->data['appearance']['showLanguageToggle']);
    }

    private function superAdminUser(): User
    {
        return User::query()->where('role', 'super_admin')->first()
            ?: User::query()->create([
                'username' => 'settings_security_admin_'.bin2hex(random_bytes(4)),
                'password' => bcrypt('password123'),
                'name' => 'Settings Security Admin',
                'role' => 'super_admin',
                'isActive' => true,
            ]);
    }
}
