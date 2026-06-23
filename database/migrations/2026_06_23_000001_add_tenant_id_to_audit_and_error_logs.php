<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs') && ! Schema::hasColumn('audit_logs', 'tenantId')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->string('tenantId')->nullable()->after('userId');
                $table->foreign('tenantId')->references('id')->on('tenants')->nullOnDelete();
                $table->index(['tenantId', 'timestamp'], 'audit_logs_tenant_timestamp_idx');
            });

            $this->backfillTenantIdFromUsers('audit_logs');
        }

        if (Schema::hasTable('archived_audit_logs') && ! Schema::hasColumn('archived_audit_logs', 'tenantId')) {
            Schema::table('archived_audit_logs', function (Blueprint $table): void {
                $table->string('tenantId')->nullable()->after('userId');
                $table->index(['tenantId', 'timestamp'], 'archived_audit_logs_tenant_timestamp_idx');
            });

            $this->backfillTenantIdFromUsers('archived_audit_logs');
        }

        if (Schema::hasTable('error_logs') && ! Schema::hasColumn('error_logs', 'tenantId')) {
            Schema::table('error_logs', function (Blueprint $table): void {
                $table->string('tenantId')->nullable()->after('userId');
                $table->foreign('tenantId')->references('id')->on('tenants')->nullOnDelete();
                $table->index(['tenantId', 'createdAt'], 'error_logs_tenant_created_idx');
            });

            $this->backfillTenantIdFromUsers('error_logs');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('error_logs') && Schema::hasColumn('error_logs', 'tenantId')) {
            Schema::table('error_logs', function (Blueprint $table): void {
                $table->dropForeign(['tenantId']);
                $table->dropIndex('error_logs_tenant_created_idx');
                $table->dropColumn('tenantId');
            });
        }

        if (Schema::hasTable('archived_audit_logs') && Schema::hasColumn('archived_audit_logs', 'tenantId')) {
            Schema::table('archived_audit_logs', function (Blueprint $table): void {
                $table->dropIndex('archived_audit_logs_tenant_timestamp_idx');
                $table->dropColumn('tenantId');
            });
        }

        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'tenantId')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->dropForeign(['tenantId']);
                $table->dropIndex('audit_logs_tenant_timestamp_idx');
                $table->dropColumn('tenantId');
            });
        }
    }

    private function backfillTenantIdFromUsers(string $table): void
    {
        DB::table($table)
            ->whereNotNull('userId')
            ->whereNull('tenantId')
            ->orderBy('id')
            ->select(['id', 'userId'])
            ->chunkById(500, function ($rows) use ($table): void {
                $userIds = $rows
                    ->pluck('userId')
                    ->filter()
                    ->unique()
                    ->values();

                if ($userIds->isEmpty()) {
                    return;
                }

                $tenantIdsByUserId = DB::table('users')
                    ->whereIn('id', $userIds)
                    ->pluck('tenantId', 'id');

                foreach ($rows as $row) {
                    $tenantId = $tenantIdsByUserId[$row->userId] ?? null;

                    if ($tenantId) {
                        DB::table($table)->where('id', $row->id)->update(['tenantId' => $tenantId]);
                    }
                }
            });
    }
};
