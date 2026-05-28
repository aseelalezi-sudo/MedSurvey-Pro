<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('audit_logs', 'ipAddress')) {
                    $table->string('ipAddress', 45)->nullable()->after('details');
                }
                if (! Schema::hasColumn('audit_logs', 'userAgent')) {
                    $table->text('userAgent')->nullable()->after('ipAddress');
                }
                if (! Schema::hasColumn('audit_logs', 'deviceName')) {
                    $table->string('deviceName')->nullable()->after('userAgent');
                }
            });
        }

        if (Schema::hasTable('archived_audit_logs')) {
            Schema::table('archived_audit_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('archived_audit_logs', 'ipAddress')) {
                    $table->string('ipAddress', 45)->nullable()->after('details');
                }
                if (! Schema::hasColumn('archived_audit_logs', 'userAgent')) {
                    $table->text('userAgent')->nullable()->after('ipAddress');
                }
                if (! Schema::hasColumn('archived_audit_logs', 'deviceName')) {
                    $table->string('deviceName')->nullable()->after('userAgent');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('archived_audit_logs')) {
            Schema::table('archived_audit_logs', function (Blueprint $table): void {
                if (Schema::hasColumn('archived_audit_logs', 'deviceName')) {
                    $table->dropColumn('deviceName');
                }
                if (Schema::hasColumn('archived_audit_logs', 'userAgent')) {
                    $table->dropColumn('userAgent');
                }
                if (Schema::hasColumn('archived_audit_logs', 'ipAddress')) {
                    $table->dropColumn('ipAddress');
                }
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                if (Schema::hasColumn('audit_logs', 'deviceName')) {
                    $table->dropColumn('deviceName');
                }
                if (Schema::hasColumn('audit_logs', 'userAgent')) {
                    $table->dropColumn('userAgent');
                }
                if (Schema::hasColumn('audit_logs', 'ipAddress')) {
                    $table->dropColumn('ipAddress');
                }
            });
        }
    }
};
