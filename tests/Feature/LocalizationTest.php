<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Concerns\CreatesTestData;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use CreatesTestData;
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

    public function test_set_locale_to_arabic(): void
    {
        $this->post(route('set-locale', ['locale' => 'ar']), [
            'referer' => route('home'),
        ])->assertRedirect(route('home'));

        $this->assertEquals('ar', session('locale'));
    }

    public function test_set_locale_to_english(): void
    {
        $this->post(route('set-locale', ['locale' => 'en']), [
            'referer' => route('home'),
        ])->assertRedirect(route('home'));

        $this->assertEquals('en', session('locale'));
    }

    public function test_invalid_locale_is_ignored_safely(): void
    {
        session()->put('locale', 'ar');

        $this->post(route('set-locale', ['locale' => 'fr']), [
            'referer' => route('home'),
        ])->assertRedirect(route('home'));

        $this->assertEquals('ar', session('locale'));
    }

    public function test_get_locale_route_no_longer_mutates_session(): void
    {
        session()->put('locale', 'en');

        $this->get('/set-locale/ar')->assertRedirect(route('home'));

        $this->assertEquals('en', session('locale'));
    }

    public function test_arabic_public_pages_render_arabic_text(): void
    {
        $this->withSession(['locale' => 'ar']);

        $this->get(route('survey.selection'))
            ->assertOk()
            ->assertSee('اختر الاستبيان');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('رأيكم يصنع');
    }

    public function test_english_public_pages_render_english_text(): void
    {
        $this->withSession(['locale' => 'en']);

        $this->get(route('survey.selection'))
            ->assertOk()
            ->assertSee('Select Survey');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Start Survey Now');
    }

    public function test_arabic_dashboard_page_renders_arabic_navigation(): void
    {
        $this->actingAs($this->adminUser)
            ->withSession(['locale' => 'ar'])
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('لوحة التحكم');
    }

    public function test_english_dashboard_page_renders_english_navigation(): void
    {
        $this->actingAs($this->adminUser)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('Dashboard');
    }

    public function test_public_survey_pages_respect_selected_locale(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();

        $this->withSession(['locale' => 'ar'])
            ->get(route('survey.take', ['surveyId' => $survey->id]))
            ->assertOk()
            ->assertSee('التالي');

        $this->withSession(['locale' => 'en'])
            ->get(route('survey.take', ['surveyId' => $survey->id]))
            ->assertOk()
            ->assertSee('Next');
    }
}
