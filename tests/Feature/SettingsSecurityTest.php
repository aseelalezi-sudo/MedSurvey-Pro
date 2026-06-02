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
}
