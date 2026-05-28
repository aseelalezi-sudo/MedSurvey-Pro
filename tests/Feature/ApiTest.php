<?php

namespace Tests\Feature;

use App\Events\SurveySubmitted;
use App\Models\AuditLog;
use App\Models\RefreshToken;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ApiTest extends TestCase
{
    // ─── Auth Tests ───

    public function test_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'username', 'name', 'role', 'isActive', 'createdAt'],
            ]);
    }

    public function test_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonStructure(['error']);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $token = $this->getAdminToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'id', 'username', 'name', 'email', 'role',
                'department', 'isActive', 'createdAt', 'lastLogin',
            ]);
    }

    public function test_me_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_refresh_returns_new_token(): void
    {
        $user = User::query()->where('username', 'admin')->firstOrFail();
        $plainRefreshToken = Str::random(80);

        RefreshToken::query()->create([
            'token' => hash('sha256', $plainRefreshToken),
            'userId' => $user->id,
            'expiresAt' => now()->addDays(7),
        ]);

        $response = $this->postJson('/api/auth/refresh', [
            'refreshToken' => $plainRefreshToken,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token']);
    }

    // ─── Surveys Tests ───

    public function test_surveys_public_index_returns_array(): void
    {
        $response = $this->getJson('/api/surveys/public');

        $response->assertOk()
            ->assertJsonIsArray();
    }

    public function test_surveys_authenticated_index_returns_array(): void
    {
        $token = $this->getAdminToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/surveys');

        $response->assertOk()
            ->assertJsonIsArray();
    }

    // ─── Responses Stats Tests ───

    public function test_responses_stats_returns_expected_shape(): void
    {
        $token = $this->getAdminToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/responses/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'totalResponses',
                'averageScore',
                'departmentScores',
                'satisfactionDistribution',
                'trendData',
            ]);
    }

    // ─── Settings Tests ───

    public function test_settings_show_returns_data(): void
    {
        $token = $this->getAdminToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/settings');

        $response->assertOk();
    }

    public function test_settings_usage_check(): void
    {
        $token = $this->getAdminToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/settings/usage-check?type=department&value=test');

        $response->assertOk()
            ->assertJsonStructure(['inUse', 'count']);
    }

    // ─── Users Tests ───

    public function test_users_index_requires_auth(): void
    {
        $response = $this->getJson('/api/users');

        $response->assertStatus(401);
    }

    public function test_users_index_returns_list(): void
    {
        $token = $this->getAdminToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/users');

        $response->assertOk()
            ->assertJsonIsArray();
    }

    // ─── Audit Tests ───

    public function test_audit_index_returns_paginated(): void
    {
        $token = $this->getAdminToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/audit');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'pagination' => ['total', 'page', 'limit', 'totalPages'],
            ]);
    }

    public function test_audit_stats_returns_expected_shape(): void
    {
        $token = $this->getAdminToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/audit/stats?days=7');

        $response->assertOk()
            ->assertJsonStructure([
                'actionStats',
                'trendData',
                'topUsers',
            ]);
    }

    public function test_authenticated_mutations_are_audited_with_device_context(): void
    {
        $token = $this->getAdminToken();
        $username = 'audited_user_'.Str::random(8);
        $createdUserId = null;
        $auditLogId = null;

        try {
            $response = $this
                ->withHeader('Authorization', "Bearer {$token}")
                ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125.0 Safari/537.36')
                ->postJson('/api/users', [
                    'username' => $username,
                    'password' => 'password123',
                    'name' => 'Audited User',
                    'email' => $username.'@example.test',
                    'role' => 'staff',
                    'isActive' => true,
                ]);

            $response->assertCreated();
            $createdUserId = $response->json('id');

            $log = AuditLog::query()
                ->where('details', 'like', '%/api/users%')
                ->latest('timestamp')
                ->firstOrFail();
            $auditLogId = $log->id;

            $this->assertSame('create_user', $log->action);
            $this->assertSame('Chrome on Windows - Desktop', $log->deviceName);
            $this->assertNotEmpty($log->ipAddress);
            $this->assertStringContainsString('Chrome/125.0', $log->userAgent);
        } finally {
            if ($createdUserId) {
                User::query()->where('id', $createdUserId)->delete();
            }
            if ($auditLogId) {
                AuditLog::query()->where('id', $auditLogId)->delete();
            }
        }
    }

    // ─── Error Logs Tests ───

    public function test_error_logs_client_accepts_report(): void
    {
        $response = $this->postJson('/api/error-logs/client', [
            'message' => 'Test error from API test',
            'level' => 'error',
            'source' => 'test',
        ]);

        $response->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_error_logs_stats_with_days(): void
    {
        $token = $this->getAdminToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/error-logs/stats?days=7');

        $response->assertOk()
            ->assertJsonStructure(['byLevel', 'byStatus', 'topSources']);
    }

    // ─── Monitoring Tests ───

    public function test_monitoring_health_returns_system_info(): void
    {
        $token = $this->getAdminToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/monitoring/health');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
                'services' => ['database', 'cache'],
                'system' => ['memory'],
            ]);
    }

    // ─── Backups Tests ───

    public function test_backups_list_returns_expected_shape(): void
    {
        $token = $this->getAdminToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/backups');

        $response->assertOk()
            ->assertJsonStructure([
                'backups',
                'config' => ['enabled', 'retentionDays', 'backupDir'],
            ]);
    }

    // ─── Tickets Tests ───

    public function test_tickets_index_requires_auth(): void
    {
        $response = $this->getJson('/api/tickets');

        $response->assertStatus(401);
    }

    public function test_low_score_response_creates_detailed_ticket_alert(): void
    {
        Event::fake([SurveySubmitted::class]);

        $surveyId = 'survey-low-score-ticket-'.Str::random(8);
        $sectionId = 'section-low-score-ticket-'.Str::random(8);
        $questionId = 'question-low-score-ticket-'.Str::random(8);
        $department = 'الباطنية';

        Survey::query()->create([
            'id' => $surveyId,
            'title' => 'Low Score Ticket Survey',
            'description' => 'Survey used by ticket alert tests.',
            'isActive' => true,
        ]);

        SurveySection::query()->create([
            'id' => $sectionId,
            'surveyId' => $surveyId,
            'title' => 'الخدمة',
            'description' => 'تقييم الخدمة',
            'sortOrder' => 1,
        ]);

        SurveyQuestion::query()->create([
            'id' => $questionId,
            'sectionId' => $sectionId,
            'type' => 'stars',
            'title' => 'تقييم الخدمة',
            'required' => true,
            'category' => 'الخدمة',
            'sortOrder' => 1,
        ]);

        try {
            $response = $this->postJson('/api/responses', [
                'surveyId' => $surveyId,
                'department' => $department,
                'patientInfo' => [
                    'name' => 'مراجع اختبار',
                    'phone' => '0500000000',
                ],
                'answers' => [
                    $questionId => 2,
                ],
            ]);

            $response->assertCreated()
                ->assertJsonPath('overallScore', 40);

            Event::assertDispatched(SurveySubmitted::class);

            $ticket = Ticket::query()->where('responseId', $response->json('id'))->firstOrFail();

            $this->assertSame(
                'تنبيه آلي: تقييم منخفض جداً (40%). المراجع أبدى عدم رضاه عن الخدمة في قسم الباطنية. يرجى المتابعة الفورية.',
                $ticket->description
            );
            $this->assertSame('medium', $ticket->priority);
            $this->assertSame('open', $ticket->status);
        } finally {
            Survey::query()->where('id', $surveyId)->delete();
        }
    }

    public function test_admin_can_delete_ticket(): void
    {
        [$survey, $ticket] = $this->createTicketForDeletionTest();
        $token = $this->getAdminToken();

        try {
            $response = $this->withHeader('Authorization', "Bearer {$token}")
                ->deleteJson("/api/tickets/{$ticket->id}");

            $response->assertOk()
                ->assertJsonPath('message', 'Ticket deleted successfully');

            $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
        } finally {
            Survey::query()->where('id', $survey->id)->delete();
        }
    }

    public function test_non_admin_cannot_delete_ticket(): void
    {
        [$survey, $ticket] = $this->createTicketForDeletionTest();
        $user = User::query()->create([
            'username' => 'ticket_staff_'.Str::random(8),
            'password' => bcrypt('password'),
            'name' => 'Ticket Staff',
            'email' => 'ticket_staff_'.Str::random(8).'@example.test',
            'role' => 'staff',
            'isActive' => true,
        ]);
        $token = JWTAuth::fromUser($user);

        try {
            $response = $this->withHeader('Authorization', "Bearer {$token}")
                ->deleteJson("/api/tickets/{$ticket->id}");

            $response->assertStatus(403);
            $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
        } finally {
            Survey::query()->where('id', $survey->id)->delete();
            $user->delete();
        }
    }

    // ─── Phase 2 & 3 Tests ───

    public function test_responses_export_downloads_csv(): void
    {
        $token = $this->getAdminToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/responses/export');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename=responses_export_'.now()->format('Ymd_His').'.csv');
    }

    public function test_responses_predictive_returns_expected_shape(): void
    {
        $token = $this->getAdminToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/responses/predictive');

        $response->assertOk()
            ->assertJsonStructure([
                'alerts',
                'stats' => [
                    'totalDepts',
                    'activeWarnings',
                    'healthIndex',
                    'totalResponsesAnalyzed',
                ],
            ]);
    }

    public function test_responses_predictive_detects_department_drop(): void
    {
        $token = $this->getAdminToken();
        $surveyId = 'survey-predictive-test-'.Str::random(8);
        $department = 'Predictive ICU '.Str::random(6);

        Survey::query()->create([
            'id' => $surveyId,
            'title' => 'Predictive Test Survey',
            'description' => 'Survey used by predictive analytics tests.',
            'isActive' => true,
        ]);

        foreach ([92, 88, 54, 50] as $index => $score) {
            SurveyResponse::query()->create([
                'id' => 'response-predictive-test-'.$index.'-'.Str::random(8),
                'surveyId' => $surveyId,
                'answers' => [],
                'department' => $department,
                'overallScore' => $score,
                'submittedAt' => now()->subDays(28 - ($index * 7)),
            ]);
        }

        try {
            $response = $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson('/api/responses/predictive');

            $response->assertOk()
                ->assertJsonFragment([
                    'department' => $department,
                    'previousAvg' => 90,
                    'currentAvg' => 52,
                ]);
        } finally {
            Survey::query()->where('id', $surveyId)->delete();
        }
    }

    public function test_archive_old_data_moves_records_older_than_three_years(): void
    {
        $user = User::query()->where('username', 'admin')->firstOrFail();
        $surveyId = 'survey-archive-test-'.Str::random(8);
        $oldResponseId = 'response-archive-old-'.Str::random(8);
        $recentResponseId = 'response-archive-recent-'.Str::random(8);
        $oldAuditId = 'audit-archive-old-'.Str::random(8);
        $recentAuditId = 'audit-archive-recent-'.Str::random(8);

        Survey::query()->create([
            'id' => $surveyId,
            'title' => 'Archive Test Survey',
            'description' => 'Survey used by archive tests.',
            'isActive' => true,
        ]);

        SurveyResponse::query()->create([
            'id' => $oldResponseId,
            'surveyId' => $surveyId,
            'answers' => ['q1' => 5],
            'department' => 'Archive Department',
            'overallScore' => 95,
            'submittedAt' => now()->subYears(3)->subDay(),
        ]);

        SurveyResponse::query()->create([
            'id' => $recentResponseId,
            'surveyId' => $surveyId,
            'answers' => ['q1' => 4],
            'department' => 'Archive Department',
            'overallScore' => 80,
            'submittedAt' => now()->subYears(2),
        ]);

        AuditLog::query()->create([
            'id' => $oldAuditId,
            'userId' => $user->id,
            'action' => 'archive_old_test',
            'details' => 'Old audit log for archive test.',
            'timestamp' => now()->subYears(3)->subDay(),
        ]);

        AuditLog::query()->create([
            'id' => $recentAuditId,
            'userId' => $user->id,
            'action' => 'archive_recent_test',
            'details' => 'Recent audit log for archive test.',
            'timestamp' => now()->subYears(2),
        ]);

        try {
            $this->artisan('archive:old-data')
                ->expectsOutput('Archived 1 survey response(s) and 1 audit log(s).')
                ->assertExitCode(0);

            $this->assertDatabaseMissing('survey_responses', ['id' => $oldResponseId]);
            $this->assertDatabaseHas('survey_responses', ['id' => $recentResponseId]);
            $this->assertDatabaseHas('archived_survey_responses', ['id' => $oldResponseId]);

            $this->assertDatabaseMissing('audit_logs', ['id' => $oldAuditId]);
            $this->assertDatabaseHas('audit_logs', ['id' => $recentAuditId]);
            $this->assertDatabaseHas('archived_audit_logs', ['id' => $oldAuditId]);
        } finally {
            Survey::query()->where('id', $surveyId)->delete();
            AuditLog::query()->whereIn('id', [$oldAuditId, $recentAuditId])->delete();
            DB::table('archived_survey_responses')->where('id', $oldResponseId)->delete();
            DB::table('archived_audit_logs')->where('id', $oldAuditId)->delete();
        }
    }

    // ─── Helpers ───

    private function createTicketForDeletionTest(): array
    {
        $survey = Survey::query()->create([
            'id' => 'survey-ticket-delete-'.Str::random(8),
            'title' => 'Ticket Delete Survey',
            'description' => 'Survey used by ticket delete tests.',
            'isActive' => true,
        ]);

        $response = SurveyResponse::query()->create([
            'id' => 'response-ticket-delete-'.Str::random(8),
            'surveyId' => $survey->id,
            'answers' => [],
            'department' => 'Delete Test Department',
            'overallScore' => 35,
            'submittedAt' => now(),
        ]);

        $ticket = Ticket::query()->create([
            'responseId' => $response->id,
            'department' => $response->department,
            'patientName' => 'Delete Test Patient',
            'patientPhone' => '0500000000',
            'priority' => 'medium',
            'status' => 'open',
            'description' => 'Delete test ticket',
        ]);

        return [$survey, $ticket];
    }

    private function getAdminToken(): string
    {
        $user = User::query()->where('username', 'admin')->first();
        if (! $user) {
            $this->markTestSkipped('Admin user not found in database');
        }

        return JWTAuth::fromUser($user);
    }
}
