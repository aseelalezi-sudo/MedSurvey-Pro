<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['userId']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('userId', 191)->nullable()->change();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreign('userId')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['userId']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('userId', 191)->nullable(false)->change();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreign('userId')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
