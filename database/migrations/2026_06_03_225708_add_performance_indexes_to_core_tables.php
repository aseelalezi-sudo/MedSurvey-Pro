<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('survey_responses', 'survey_responses_score_submitted_idx', function (Blueprint $table): void {
            $table->index(['overallScore', 'submittedAt'], 'survey_responses_score_submitted_idx');
        });

        $this->addIndexIfMissing('survey_responses', 'survey_responses_gender_submitted_idx', function (Blueprint $table): void {
            $table->index(['gender', 'submittedAt'], 'survey_responses_gender_submitted_idx');
        });

        $this->addIndexIfMissing('survey_responses', 'survey_responses_visit_type_submitted_idx', function (Blueprint $table): void {
            $table->index(['visitType', 'submittedAt'], 'survey_responses_visit_type_submitted_idx');
        });

        $this->addIndexIfMissing('survey_responses', 'survey_responses_age_group_submitted_idx', function (Blueprint $table): void {
            $table->index(['ageGroup', 'submittedAt'], 'survey_responses_age_group_submitted_idx');
        });

        $this->addIndexIfMissing('tickets', 'tickets_priority_status_idx', function (Blueprint $table): void {
            $table->index(['priority', 'status'], 'tickets_priority_status_idx');
        });

        $this->addIndexIfMissing('tickets', 'tickets_resolved_at_idx', function (Blueprint $table): void {
            $table->index('resolvedAt', 'tickets_resolved_at_idx');
        });

        $this->addIndexIfMissing('surveys', 'surveys_tenant_active_idx', function (Blueprint $table): void {
            $table->index(['tenantId', 'isActive'], 'surveys_tenant_active_idx');
        });

        $this->addIndexIfMissing('surveys', 'surveys_active_created_idx', function (Blueprint $table): void {
            $table->index(['isActive', 'createdAt'], 'surveys_active_created_idx');
        });

        $this->addIndexIfMissing('survey_sections', 'survey_sections_survey_sort_idx', function (Blueprint $table): void {
            $table->index(['surveyId', 'sortOrder'], 'survey_sections_survey_sort_idx');
        });

        $this->addIndexIfMissing('survey_questions', 'survey_questions_section_sort_idx', function (Blueprint $table): void {
            $table->index(['sectionId', 'sortOrder'], 'survey_questions_section_sort_idx');
        });

        $this->addIndexIfMissing('users', 'users_role_idx', function (Blueprint $table): void {
            $table->index('role', 'users_role_idx');
        });

        $this->addIndexIfMissing('users', 'users_active_idx', function (Blueprint $table): void {
            $table->index('isActive', 'users_active_idx');
        });

        $this->addIndexIfMissing('users', 'users_tenant_active_idx', function (Blueprint $table): void {
            $table->index(['tenantId', 'isActive'], 'users_tenant_active_idx');
        });
    }

    public function down(): void
    {
        // Drop FK-referencing columns first, then the index, then recreate FK.
        $this->recreateForeignKeyThenDropIndex('users', 'users_tenant_active_idx', 'tenantId', 'tenants', 'id', 'nullOnDelete');
        $this->recreateForeignKeyThenDropIndex('surveys', 'surveys_tenant_active_idx', 'tenantId', 'tenants', 'id', 'nullOnDelete');

        $this->dropIndexIfExists('users', 'users_active_idx');
        $this->dropIndexIfExists('users', 'users_role_idx');

        $this->dropIndexIfExists('survey_questions', 'survey_questions_section_sort_idx');
        $this->dropIndexIfExists('survey_sections', 'survey_sections_survey_sort_idx');

        $this->dropIndexIfExists('surveys', 'surveys_active_created_idx');

        $this->dropIndexIfExists('tickets', 'tickets_resolved_at_idx');
        $this->dropIndexIfExists('tickets', 'tickets_priority_status_idx');

        $this->dropIndexIfExists('survey_responses', 'survey_responses_age_group_submitted_idx');
        $this->dropIndexIfExists('survey_responses', 'survey_responses_visit_type_submitted_idx');
        $this->dropIndexIfExists('survey_responses', 'survey_responses_gender_submitted_idx');
        $this->dropIndexIfExists('survey_responses', 'survey_responses_score_submitted_idx');
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
        $result = DB::selectOne(
            'SELECT COUNT(1) AS count FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$tableName, $indexName]
        );

        return (int) ($result->count ?? 0) > 0;
    }

    /**
     * Drop a foreign key, then drop the index, then recreate the foreign key.
     * Used when an index cannot be dropped because it is used by a FK constraint.
     */
    private function recreateForeignKeyThenDropIndex(string $tableName, string $indexName, string $column, string $foreignTable, string $foreignColumn, string $onDelete): void
    {
        if (! $this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($column): void {
            $table->dropForeign([$column]);
        });

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });

        Schema::table($tableName, function (Blueprint $table) use ($column, $foreignTable, $foreignColumn, $onDelete): void {
            if ($onDelete === 'nullOnDelete') {
                $table->foreign($column)->references($foreignColumn)->on($foreignTable)->nullOnDelete();
            } else {
                $table->foreign($column)->references($foreignColumn)->on($foreignTable)->cascadeOnDelete();
            }
        });
    }
};
