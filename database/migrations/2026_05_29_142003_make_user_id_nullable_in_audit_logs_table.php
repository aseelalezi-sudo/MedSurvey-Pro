<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignKeys('audit_logs', 'userId');

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('userId')->nullable()->change();
        });

        $this->ensureAuditLogUserForeignKey();

        Schema::table('archived_audit_logs', function (Blueprint $table): void {
            $table->string('userId')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('archived_audit_logs', function (Blueprint $table): void {
            $table->string('userId')->nullable(false)->change();
        });

        $this->dropForeignKeys('audit_logs', 'userId');

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('userId')->nullable(false)->change();
        });

        $this->ensureAuditLogUserForeignKey();
    }

    private function ensureAuditLogUserForeignKey(): void
    {
        if ($this->foreignKeyNames('audit_logs', 'userId') !== []) {
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    private function dropForeignKeys(string $table, string $column): void
    {
        foreach ($this->foreignKeyNames($table, $column) as $foreignKey) {
            Schema::table($table, function (Blueprint $table) use ($foreignKey): void {
                $table->dropForeign($foreignKey);
            });
        }
    }

    /**
     * @return list<string>
     */
    private function foreignKeyNames(string $table, string $column): array
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return [];
        }

        $database = DB::connection()->getDatabaseName();

        return collect(DB::select(
            'select constraint_name from information_schema.key_column_usage where table_schema = ? and table_name = ? and column_name = ? and referenced_table_name is not null',
            [$database, $table, $column],
        ))
            ->pluck('constraint_name')
            ->filter()
            ->values()
            ->all();
    }
};
