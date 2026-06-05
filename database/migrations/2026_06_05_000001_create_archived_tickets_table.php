<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('archived_tickets')) {
            Schema::create('archived_tickets', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('responseId')->index();
                $table->string('department');
                $table->string('patientName');
                $table->string('patientPhone')->nullable();
                $table->enum('priority', ['high', 'medium', 'low']);
                $table->enum('status', ['open', 'in_progress', 'resolved']);
                $table->text('description');
                $table->timestamp('createdAt')->nullable();
                $table->timestamp('resolvedAt')->nullable();
                $table->text('resolutionNotes')->nullable();
                $table->string('assignedTo')->nullable();
                $table->timestamp('archivedAt')->useCurrent();
                $table->index('status');
                $table->index('department');
                $table->index('createdAt');
                $table->index('archivedAt');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_tickets');
    }
};
