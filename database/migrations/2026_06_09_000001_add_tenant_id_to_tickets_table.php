<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'tenantId')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->string('tenantId')->nullable()->after('assignedTo');
                $table->foreign('tenantId')->references('id')->on('tenants')->nullOnDelete();
                $table->index('tenantId', 'tickets_tenant_id_idx');
            });

            // Backfill tenantId from the linked survey_response → survey chain
            DB::statement('
                UPDATE tickets t
                INNER JOIN survey_responses sr ON t.responseId = sr.id
                SET t.tenantId = sr.tenantId
                WHERE t.tenantId IS NULL AND sr.tenantId IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tickets', 'tenantId')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->dropForeign(['tenantId']);
                $table->dropIndex('tickets_tenant_id_idx');
                $table->dropColumn('tenantId');
            });
        }
    }
};
