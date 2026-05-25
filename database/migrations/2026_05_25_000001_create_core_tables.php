<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('name');
                $table->timestamp('createdAt')->useCurrent();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('username')->unique();
                $table->string('password');
                $table->string('name');
                $table->string('email')->default('');
                $table->enum('role', ['super_admin', 'admin', 'unit_manager', 'head_of_department', 'staff']);
                $table->string('department')->nullable();
                $table->timestamp('createdAt')->useCurrent();
                $table->timestamp('lastLogin')->nullable();
                $table->boolean('isActive')->default(true);
                $table->text('avatar')->nullable();
                $table->string('tenantId')->nullable();
                $table->foreign('tenantId')->references('id')->on('tenants')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('tenantId')->nullable()->unique();
                $table->json('data');
                $table->foreign('tenantId')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('surveys')) {
            Schema::create('surveys', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('title');
                $table->text('description');
                $table->boolean('isActive')->default(true);
                $table->boolean('requireName')->default(false);
                $table->boolean('requirePhone')->default(false);
                $table->json('assignedDepartments')->nullable();
                $table->json('tips')->nullable();
                $table->timestamp('createdAt')->useCurrent();
                $table->string('tenantId')->nullable();
                $table->foreign('tenantId')->references('id')->on('tenants')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('survey_sections')) {
            Schema::create('survey_sections', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('surveyId');
                $table->string('title');
                $table->text('description');
                $table->string('icon')->default('clipboard-check');
                $table->integer('sortOrder')->default(0);
                $table->foreign('surveyId')->references('id')->on('surveys')->cascadeOnDelete();
                $table->index('surveyId');
            });
        }

        if (! Schema::hasTable('survey_questions')) {
            Schema::create('survey_questions', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('sectionId');
                $table->enum('type', ['rating', 'stars', 'emoji', 'text', 'multiple_choice', 'yes_no', 'nps'])->default('stars');
                $table->text('title');
                $table->text('description')->nullable();
                $table->boolean('required')->default(false);
                $table->string('category')->default('');
                $table->json('options')->nullable();
                $table->json('followUp')->nullable();
                $table->integer('sortOrder')->default(0);
                $table->foreign('sectionId')->references('id')->on('survey_sections')->cascadeOnDelete();
                $table->index('sectionId');
                $table->index('type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('survey_sections');
        Schema::dropIfExists('surveys');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('users');
        Schema::dropIfExists('tenants');
    }
};
