<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_responses', function (Blueprint $table): void {
            $table->index(['tenantId', 'submittedAt'], 'survey_responses_tenant_submitted_idx');
            $table->index(['tenantId', 'department', 'submittedAt'], 'survey_responses_tenant_dept_submitted_idx');
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->index(['department', 'status', 'createdAt'], 'tickets_dept_status_created_idx');
        });
    }

    public function down(): void
    {
        $responseIndexes = ['survey_responses_tenant_dept_submitted_idx', 'survey_responses_tenant_submitted_idx'];
        $support = collect(Schema::getIndexes('survey_responses'))->contains(
            fn (array $index): bool => $index['columns'][0] === 'tenantId'
                && ! in_array($index['name'], $responseIndexes, true)
        );

        // InnoDB can discard its implicit FK index when a covering index is added.
        // Restore a supporting index at rollback time, including on existing schemas.
        if (! $support) {
            Schema::table('survey_responses', function (Blueprint $table): void {
                $table->index('tenantId', 'survey_responses_tenantid_foreign');
            });
        }

        foreach (['tickets' => ['tickets_dept_status_created_idx'], 'survey_responses' => $responseIndexes] as $name => $indexes) {
            foreach ($indexes as $index) {
                if (Schema::hasIndex($name, $index)) {
                    Schema::table($name, fn (Blueprint $table) => $table->dropIndex($index));
                }
            }
        }
    }
};
