<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'isActive')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->boolean('isActive')->default(true)->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'isActive')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->dropColumn('isActive');
            });
        }
    }
};
