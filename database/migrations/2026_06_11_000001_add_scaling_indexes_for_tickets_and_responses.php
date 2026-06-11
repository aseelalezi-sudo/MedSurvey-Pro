<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tickets', 'tenantId')) {
            DB::statement('
                UPDATE tickets
                SET tenantId = (
                    SELECT sr.tenantId
                    FROM survey_responses sr
                    WHERE sr.id = tickets.responseId
                      AND sr.tenantId IS NOT NULL
                    LIMIT 1
                )
                WHERE tenantId IS NULL
                  AND EXISTS (
                      SELECT 1
                      FROM survey_responses sr
                      WHERE sr.id = tickets.responseId
                        AND sr.tenantId IS NOT NULL
                  )
            ');

            $this->addIndexIfMissing('tickets', 'tickets_tenant_status_created_idx', function (Blueprint $table): void {
                $table->index(['tenantId', 'status', 'createdAt'], 'tickets_tenant_status_created_idx');
            });

            $this->addIndexIfMissing('tickets', 'tickets_tenant_dept_status_created_idx', function (Blueprint $table): void {
                $table->index(['tenantId', 'department', 'status', 'createdAt'], 'tickets_tenant_dept_status_created_idx');
            });
        }

        if (Schema::hasColumn('survey_responses', 'tenantId')) {
            $this->addIndexIfMissing('survey_responses', 'survey_responses_tenant_score_submitted_idx', function (Blueprint $table): void {
                $table->index(['tenantId', 'overallScore', 'submittedAt'], 'survey_responses_tenant_score_submitted_idx');
            });

            $this->addIndexIfMissing('survey_responses', 'survey_responses_tenant_dept_score_submitted_idx', function (Blueprint $table): void {
                $table->index(['tenantId', 'department', 'overallScore', 'submittedAt'], 'survey_responses_tenant_dept_score_submitted_idx');
            });
        }

        if (Schema::hasTable('survey_answers')) {
            $this->addIndexIfMissing('survey_answers', 'survey_answers_question_response_idx', function (Blueprint $table): void {
                $table->index(['questionId', 'responseId'], 'survey_answers_question_response_idx');
            });
        }

        if (Schema::hasTable('audit_logs')) {
            $this->addIndexIfMissing('audit_logs', 'audit_logs_action_timestamp_idx', function (Blueprint $table): void {
                $table->index(['action', 'timestamp'], 'audit_logs_action_timestamp_idx');
            });
        }

        if (Schema::hasTable('error_logs')) {
            $this->addIndexIfMissing('error_logs', 'error_logs_level_status_created_idx', function (Blueprint $table): void {
                $table->index(['level', 'status', 'createdAt'], 'error_logs_level_status_created_idx');
            });

            $this->addIndexIfMissing('error_logs', 'error_logs_created_source_idx', function (Blueprint $table): void {
                $table->index(['createdAt', 'source'], 'error_logs_created_source_idx');
            });
        }

        if (Schema::hasTable('archived_tickets')) {
            if (! Schema::hasColumn('archived_tickets', 'tenantId')) {
                Schema::table('archived_tickets', function (Blueprint $table): void {
                    $table->string('tenantId')->nullable()->after('responseId');
                });
            }

            $this->addIndexIfMissing('archived_tickets', 'archived_tickets_tenant_archived_idx', function (Blueprint $table): void {
                $table->index(['tenantId', 'archivedAt'], 'archived_tickets_tenant_archived_idx');
            });
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('archived_tickets', 'archived_tickets_tenant_archived_idx');
        if (Schema::hasColumn('archived_tickets', 'tenantId')) {
            Schema::table('archived_tickets', function (Blueprint $table): void {
                $table->dropColumn('tenantId');
            });
        }
        $this->dropIndexIfExists('error_logs', 'error_logs_created_source_idx');
        $this->dropIndexIfExists('error_logs', 'error_logs_level_status_created_idx');
        $this->dropIndexIfExists('audit_logs', 'audit_logs_action_timestamp_idx');
        $this->dropIndexIfExists('survey_answers', 'survey_answers_question_response_idx');
        $this->dropIndexIfExists('survey_responses', 'survey_responses_tenant_dept_score_submitted_idx');
        $this->dropIndexIfExists('survey_responses', 'survey_responses_tenant_score_submitted_idx');
        $this->dropIndexIfExists('tickets', 'tickets_tenant_dept_status_created_idx');
        $this->dropIndexIfExists('tickets', 'tickets_tenant_status_created_idx');
    }

    private function addIndexIfMissing(string $tableName, string $indexName, callable $callback): void
    {
        if (! $this->indexExists($tableName, $indexName)) {
            Schema::table($tableName, $callback);
        }
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->dropIndex($indexName);
            });
        }
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return Schema::getConnection()->getSchemaBuilder()->hasIndex($tableName, $indexName);
        }

        $result = DB::selectOne(
            'SELECT COUNT(1) AS count FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$tableName, $indexName]
        );

        return (int) ($result->count ?? 0) > 0;
    }
};
