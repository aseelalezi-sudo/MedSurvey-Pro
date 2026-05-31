<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WebViewsTest extends TestCase
{
    use DatabaseTransactions;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::query()->where('role', 'super_admin')->first();
        if (! $this->adminUser) {
            $this->adminUser = User::query()->create([
                'username' => 'web_test_admin',
                'password' => bcrypt('password123'),
                'name' => 'Web Test Admin',
                'role' => 'super_admin',
                'isActive' => true,
            ]);
        }
    }

    public function test_public_pages_load(): void
    {
        $this->get(route('home'))->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('survey.selection'))->assertOk();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_dashboard_pages_load(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('dashboard.index'));
        $response->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.responses'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.reports'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.predictive'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.tickets'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.hall-of-fame'))
            ->assertOk();
    }

    public function test_admin_only_dashboard_pages_load(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('dashboard.surveys'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.users'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.settings'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.audit'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.monitoring'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.error-logs'))
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->get(route('dashboard.backups'))
            ->assertOk();
    }

    public function test_survey_taking_page_loads_with_valid_id(): void
    {
        $survey = Survey::query()->first();
        if (! $survey) {
            $survey = Survey::query()->create([
                'id' => 'test-survey-web',
                'title' => 'Web Test Survey',
                'description' => 'Test Description',
                'isActive' => true,
            ]);
        }

        $this->get(route('survey.take', ['surveyId' => $survey->id]))
            ->assertOk();
    }
}
