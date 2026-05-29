<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            try {
                $table->dropForeign('audit_logs_userId_fkey');
            } catch (\Throwable $e) {
                try {
                    $table->dropForeign('audit_logs_userid_foreign');
                } catch (\Throwable $ex) {
                    // Ignore
                }
            }
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('userId')->nullable()->change();
            $table->foreign('userId', 'audit_logs_userId_fkey')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('archived_audit_logs', function (Blueprint $table): void {
            $table->string('userId')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('archived_audit_logs', function (Blueprint $table): void {
            $table->string('userId')->nullable(false)->change();
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            try {
                $table->dropForeign('audit_logs_userId_fkey');
            } catch (\Throwable $e) {
                try {
                    $table->dropForeign('audit_logs_userid_foreign');
                } catch (\Throwable $ex) {
                    // Ignore
                }
            }
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('userId')->nullable(false)->change();
            $table->foreign('userId', 'audit_logs_userId_fkey')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
