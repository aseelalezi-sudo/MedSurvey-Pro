<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('archived_survey_responses', 'tenantId')) {
            Schema::table('archived_survey_responses', function (Blueprint $table): void {
                $table->string('tenantId')->nullable()->after('surveyId');
                $table->index('tenantId', 'archived_survey_responses_tenantid_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('archived_survey_responses', 'tenantId')) {
            Schema::table('archived_survey_responses', function (Blueprint $table): void {
                $table->dropIndex('archived_survey_responses_tenantid_index');
                $table->dropColumn('tenantId');
            });
        }
    }
};
