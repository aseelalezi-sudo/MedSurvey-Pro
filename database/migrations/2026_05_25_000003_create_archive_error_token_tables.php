<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('archived_survey_responses')) {
            Schema::create('archived_survey_responses', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('surveyId');
                $table->json('answers');
                $table->string('patientName')->nullable();
                $table->string('patientPhone')->nullable();
                $table->string('ageGroup')->nullable();
                $table->string('gender')->nullable();
                $table->string('visitType')->nullable();
                $table->string('department');
                $table->integer('overallScore');
                $table->timestamp('submittedAt');
                $table->timestamp('archivedAt')->useCurrent();
                $table->index('department');
                $table->index('submittedAt');
            });
        }

        if (!Schema::hasTable('archived_audit_logs')) {
            Schema::create('archived_audit_logs', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('userId');
                $table->string('action');
                $table->text('details');
                $table->timestamp('timestamp');
                $table->timestamp('archivedAt')->useCurrent();
                $table->index('userId');
                $table->index('timestamp');
            });
        }

        if (!Schema::hasTable('error_logs')) {
            Schema::create('error_logs', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('level')->default('error');
                $table->text('message');
                $table->text('stack')->nullable();
                $table->string('source')->nullable();
                $table->json('metadata')->nullable();
                $table->string('status')->default('new');
                $table->text('resolutionNotes')->nullable();
                $table->integer('count')->default(1);
                $table->timestamp('createdAt')->useCurrent();
                $table->timestamp('resolvedAt')->nullable();
                $table->string('userId')->nullable();
                $table->index('level');
                $table->index('status');
                $table->index('source');
                $table->index('createdAt');
                $table->index(['level', 'status']);
            });
        }

        if (!Schema::hasTable('refresh_tokens')) {
            Schema::create('refresh_tokens', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('token', 500)->unique();
                $table->string('userId');
                $table->timestamp('expiresAt');
                $table->timestamp('createdAt')->useCurrent();
                $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
                $table->index('userId');
                $table->index('expiresAt');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
        Schema::dropIfExists('error_logs');
        Schema::dropIfExists('archived_audit_logs');
        Schema::dropIfExists('archived_survey_responses');
    }
};

