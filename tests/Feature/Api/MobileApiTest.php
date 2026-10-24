<?php

namespace Tests\Feature\Api;

use App\Models\Settings;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\CreatesTestData;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use CreatesTestData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_collector_can_login_and_receives_a_token(): void
    {
        $user = $this->userWithRole('staff');

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'Password123!',
            'deviceName' => 'Test Android',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'tokenType',
                'expiresAt',
                'user' => ['id', 'username', 'name', 'role', 'department', 'tenantId'],
            ]);

        $this->assertSame('Bearer', $response->json('tokenType'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Test Android',
        ]);
    }

    public function test_login_is_rejected_for_wrong_password_or_inactive_account(): void
    {
        $user = $this->userWithRole('staff');

        $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('username');

        $user->forceFill(['isActive' => false])->save();

        $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'Password123!',
        ])->assertStatus(422)->assertJsonValidationErrors('username');
    }

    public function test_bootstrap_returns_surveys_and_form_lists(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();
        $user = $this->userWithRole('staff');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/bootstrap');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'username'],
                'surveys' => [
                    ['id', 'title', 'sections' => [['id', 'title', 'questions']]],
                ],
                'settings' => ['departments', 'ageGroups', 'visitTypes', 'surveySettings'],
                'serverTime',
            ]);

        $this->assertContains(
            $survey->id,
            collect($response->json('surveys'))->pluck('id')->all()
        );
        $this->assertContains(
            'Emergency',
            collect($response->json('settings.departments'))->pluck('name')->all()
        );
    }

    public function test_bootstrap_exposes_hospital_copy_for_the_home_hero(): void
    {
        Tenant::query()->create(['id' => 'tenant-hero', 'name' => 'Hero Tenant']);
        Settings::query()->create([
            'tenantId' => 'tenant-hero',
            'data' => [
                'hospital' => [
                    'name' => 'Hero Hospital',
                    'description' => 'رعاية صحية متكاملة لعائلتك.',
                    'welcomeMessage' => 'أهلاً بكم، نسعد بمشاركتنا رأيكم.',
                ],
                'departments' => [],
                'ageGroups' => [],
                'visitTypes' => [],
                'surveySettings' => ['allowAnonymous' => true],
            ],
        ]);

        Sanctum::actingAs($this->userWithRole('staff', ['tenantId' => 'tenant-hero']));

        $this->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('settings.hospital.name', 'Hero Hospital')
            ->assertJsonPath(
                'settings.hospital.description',
                'رعاية صحية متكاملة لعائلتك.'
            )
            ->assertJsonPath(
                'settings.hospital.welcomeMessage',
                'أهلاً بكم، نسعد بمشاركتنا رأيكم.'
            );
    }

    public function test_global_survey_uses_the_collectors_tenant_settings(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();
        $question = $survey->sections->first()->questions->first();

        Tenant::query()->create(['id' => 'tenant-own', 'name' => 'Own Tenant']);
        Settings::query()->create([
            'tenantId' => 'tenant-own',
            'data' => [
                'departments' => [
                    ['id' => 'dept-own', 'name' => 'Tenant Own Department', 'isActive' => true],
                ],
                'ageGroups' => [
                    ['id' => 'age-own', 'label' => '18 - 30 سنة', 'isActive' => true],
                ],
                'visitTypes' => [
                    ['id' => 'visit-own', 'label' => 'زيارة طارئة', 'isActive' => true],
                ],
                'surveySettings' => [
                    'allowAnonymous' => true,
                    'requireAllQuestions' => false,
                ],
            ],
        ]);

        $user = $this->userWithRole('staff', ['tenantId' => 'tenant-own']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('settings.departments.0.name', 'Tenant Own Department');

        $this->postJson('/api/v1/responses', [
            'clientResponseId' => 'android-'.bin2hex(random_bytes(8)),
            'surveyId' => $survey->id,
            'answers' => [
                ['questionId' => $question->id, 'value' => 5],
            ],
            'department' => 'Tenant Own Department',
            'patientInfo' => [
                'name' => 'Tenant Patient',
                'phone' => '0555123456',
                'ageGroup' => '18 - 30 سنة',
                'gender' => 'ذكر',
                'visitType' => 'زيارة طارئة',
            ],
        ])->assertCreated();
    }

    public function test_global_survey_uses_the_collectors_tenant_require_all_questions_setting(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();
        $question = $survey->sections->first()->questions->first();
        $question->forceFill(['required' => false])->save();

        Tenant::query()->create(['id' => 'tenant-all-questions', 'name' => 'All Questions Tenant']);
        Settings::query()->create([
            'tenantId' => 'tenant-all-questions',
            'data' => [
                'departments' => [
                    ['id' => 'dept-all', 'name' => 'All Questions Department', 'isActive' => true],
                ],
                'ageGroups' => [
                    ['id' => 'age-all', 'label' => '18 - 30 سنة', 'isActive' => true],
                ],
                'visitTypes' => [
                    ['id' => 'visit-all', 'label' => 'زيارة طارئة', 'isActive' => true],
                ],
                'surveySettings' => [
                    'allowAnonymous' => true,
                    'requireAllQuestions' => true,
                ],
            ],
        ]);

        $user = $this->userWithRole('staff', ['tenantId' => 'tenant-all-questions']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/responses', [
            'clientResponseId' => 'android-'.bin2hex(random_bytes(8)),
            'surveyId' => $survey->id,
            'answers' => [],
            'department' => 'All Questions Department',
            'patientInfo' => [
                'name' => 'All Questions Patient',
                'phone' => '0555123456',
                'ageGroup' => '18 - 30 سنة',
                'gender' => 'ذكر',
                'visitType' => 'زيارة طارئة',
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('answers');
    }

    public function test_offline_response_is_stored_once_even_when_retried(): void
    {
        $survey = $this->createActiveSurveyWithQuestion();
        $question = $survey->sections->first()->questions->first();
        $user = $this->userWithRole('staff');
        $clientResponseId = 'android-'.bin2hex(random_bytes(8));

        $payload = [
            'clientResponseId' => $clientResponseId,
            'surveyId' => $survey->id,
            'answers' => [
                ['questionId' => $question->id, 'value' => 1],
            ],
            'department' => 'Emergency',
            'patientInfo' => [
                'name' => 'Android Patient',
                'phone' => '0555123456',
                'ageGroup' => '18 - 30 سنة',
                'gender' => 'ذكر',
                'visitType' => 'زيارة طارئة',
            ],
        ];

        Sanctum::actingAs($user);

        $first = $this->postJson('/api/v1/responses', $payload);
        $first->assertCreated()
            ->assertJson([
                'clientResponseId' => $clientResponseId,
                'duplicate' => false,
            ]);

        $second = $this->postJson('/api/v1/responses', $payload);
        $second->assertOk()
            ->assertJson([
                'clientResponseId' => $clientResponseId,
                'duplicate' => true,
            ]);

        $this->assertSame(
            1,
            SurveyResponse::query()->where('clientResponseId', $clientResponseId)->count()
        );
        $this->assertSame(1, Ticket::query()->count());

        $responseId = $first->json('id');
        $this->assertDatabaseHas('survey_responses', [
            'id' => $responseId,
            'collectorId' => $user->id,
        ]);
    }

    public function test_collector_cannot_submit_to_another_tenants_survey(): void
    {
        Tenant::query()->create(['id' => 'tenant-own', 'name' => 'Own Tenant']);
        Tenant::query()->create(['id' => 'tenant-other', 'name' => 'Other Tenant']);

        $survey = Survey::query()->create([
            'id' => 'tenant-survey-'.bin2hex(random_bytes(4)),
            'title' => 'Tenant Only Survey',
            'description' => 'Should not be visible to outsiders',
            'isActive' => true,
            'tenantId' => 'tenant-other',
        ]);

        $user = $this->userWithRole('staff', ['tenantId' => 'tenant-own']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/responses', [
            'clientResponseId' => 'android-'.bin2hex(random_bytes(8)),
            'surveyId' => $survey->id,
            'answers' => [],
            'department' => 'Emergency',
        ])->assertStatus(422);

        $this->assertSame(0, SurveyResponse::query()->where('surveyId', $survey->id)->count());
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = $this->userWithRole('staff');
        $token = $user->createToken('android')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }
}
