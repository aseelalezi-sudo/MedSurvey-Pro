<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('survey_responses')) {
            Schema::create('survey_responses', function (Blueprint $table): void {
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
                $table->timestamp('submittedAt')->useCurrent();
                $table->string('tenantId')->nullable();
                $table->foreign('tenantId')->references('id')->on('tenants')->nullOnDelete();
                $table->foreign('surveyId')->references('id')->on('surveys')->cascadeOnDelete();
                $table->index('department');
                $table->index('submittedAt');
                $table->index('patientName');
                $table->index('patientPhone');
                $table->index('overallScore');
                $table->index('surveyId');
                $table->index(['department', 'submittedAt']);
            });
        }

        if (!Schema::hasTable('tickets')) {
            Schema::create('tickets', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('responseId')->unique();
                $table->string('department');
                $table->string('patientName');
                $table->string('patientPhone')->nullable();
                $table->enum('priority', ['high', 'medium', 'low']);
                $table->enum('status', ['open', 'in_progress', 'resolved'])->default('open');
                $table->text('description');
                $table->timestamp('createdAt')->useCurrent();
                $table->timestamp('resolvedAt')->nullable();
                $table->text('resolutionNotes')->nullable();
                $table->string('assignedTo')->nullable();
                $table->foreign('responseId')->references('id')->on('survey_responses')->cascadeOnDelete();
                $table->index('status');
                $table->index('department');
                $table->index('createdAt');
                $table->index('priority');
                $table->index('assignedTo');
            });
        }

        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('userId');
                $table->string('action');
                $table->text('details');
                $table->timestamp('timestamp')->useCurrent();
                $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
                $table->index('userId');
                $table->index('timestamp');
                $table->index('action');
                $table->index(['userId', 'timestamp']);
            });
        }

        if (!Schema::hasTable('survey_answers')) {
            Schema::create('survey_answers', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('responseId');
                $table->string('questionId');
                $table->text('value');
                $table->foreign('responseId')->references('id')->on('survey_responses')->cascadeOnDelete();
                $table->foreign('questionId')->references('id')->on('survey_questions')->cascadeOnDelete();
                $table->unique(['responseId', 'questionId']);
                $table->index('questionId');
                $table->index('responseId');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_answers');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('survey_responses');
    }
};

