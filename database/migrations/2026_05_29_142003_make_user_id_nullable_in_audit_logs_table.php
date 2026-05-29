<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['userId']);
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('userId')->nullable()->change();
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
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
            $table->dropForeign(['userId']);
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('userId')->nullable(false)->change();
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};