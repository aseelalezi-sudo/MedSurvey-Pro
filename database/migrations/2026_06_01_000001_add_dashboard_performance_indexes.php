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
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex('tickets_dept_status_created_idx');
        });

        Schema::table('survey_responses', function (Blueprint $table): void {
            $table->dropIndex('survey_responses_tenant_dept_submitted_idx');
            $table->dropIndex('survey_responses_tenant_submitted_idx');
        });
    }
};
