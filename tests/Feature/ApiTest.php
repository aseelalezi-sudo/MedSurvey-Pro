<?php

namespace Tests\Feature;

use App\Models\User;
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
        $token = $this->getAdminToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/refresh');

        $response->assertOk()
            ->assertJsonStructure(['token']);
    }

    // ─── Surveys Tests ───

    public function test_surveys_index_returns_array(): void
    {
        $response = $this->getJson('/api/surveys');

        $response->assertOk()
            ->assertJsonIsArray();
    }

    public function test_surveys_index_active_filter(): void
    {
        $response = $this->getJson('/api/surveys?active=true');

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
        $response = $this->getJson('/api/settings');

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

    // ─── Phase 2 & 3 Tests ───

    public function test_responses_export_downloads_csv(): void
    {
        $token = $this->getAdminToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/responses/export');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename=responses_export_' . now()->format('Ymd_His') . '.csv');
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

    // ─── Helpers ───

    private function getAdminToken(): string
    {
        $user = User::query()->where('username', 'admin')->first();
        if (! $user) {
            $this->markTestSkipped('Admin user not found in database');
        }

        return JWTAuth::fromUser($user);
    }
}
