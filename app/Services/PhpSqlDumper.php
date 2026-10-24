<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Writes a SQL dump using only PHP and the database connection.
 *
 * Shared hosts often have no `mysqldump` binary, or disable `proc_open`, which
 * makes the external-process backup impossible. This produces the same kind of
 * file (DROP/CREATE TABLE plus multi-row INSERT statements) so verification and
 * `mysql < file` restores keep working. Views, triggers and routines are not
 * included; the application does not use them.
 */
class PhpSqlDumper
{
    private const ROWS_PER_INSERT = 200;

    /**
     * @param  list<string>|null  $only  Restrict the dump to these tables (tests).
     */
    public function dump(string $path, ?array $only = null): void
    {
        $pdo = DB::connection()->getPdo();

        $handle = @fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to write backup file: '.$path);
        }

        try {
            $this->write($handle, "-- MedSurvey Pro SQL dump (PHP)\n");
            $this->write($handle, '-- Generated: '.now()->toDateTimeString()."\n\n");
            $this->write($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            foreach ($this->tables($pdo) as $table) {
                if ($only !== null && ! in_array($table, $only, true)) {
                    continue;
                }

                $this->dumpTable($pdo, $handle, $table);
            }

            $this->write($handle, "SET FOREIGN_KEY_CHECKS=1;\nSET UNIQUE_CHECKS=1;\n-- Dump completed\n");
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return list<string>
     */
    private function tables(PDO $pdo): array
    {
        $tables = [];
        foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $row) {
            $tables[] = (string) $row[0];
        }

        return $tables;
    }

    /**
     * @param  resource  $handle
     */
    private function dumpTable(PDO $pdo, $handle, string $table): void
    {
        $quoted = $this->identifier($table);

        $create = $pdo->query('SHOW CREATE TABLE '.$quoted)->fetchAll(PDO::FETCH_NUM)[0] ?? null;
        if (! is_array($create) || ! isset($create[1])) {
            throw new RuntimeException('Unable to read the structure of table '.$table);
        }

        $this->write($handle, "--\n-- Table structure for {$quoted}\n--\n\n");
        $this->write($handle, 'DROP TABLE IF EXISTS '.$quoted.";\n".$create[1].";\n\n");

        $columns = null;
        $buffer = [];

        // Stream rows instead of loading a whole table into memory.
        $buffered = $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

        try {
            $statement = $pdo->query('SELECT * FROM '.$quoted, PDO::FETCH_ASSOC);

            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                $columns ??= implode(', ', array_map(fn ($name) => $this->identifier((string) $name), array_keys($row)));
                $buffer[] = '('.implode(', ', array_map(fn ($value) => $this->literal($pdo, $value), $row)).')';

                if (count($buffer) >= self::ROWS_PER_INSERT) {
                    $this->flush($handle, $quoted, $columns, $buffer);
                    $buffer = [];
                }
            }

            $statement->closeCursor();
        } finally {
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buffered);
        }

        if ($buffer !== []) {
            $this->flush($handle, $quoted, (string) $columns, $buffer);
        }

        $this->write($handle, "\n");
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $rows
     */
    private function flush($handle, string $table, string $columns, array $rows): void
    {
        $this->write($handle, "INSERT INTO {$table} ({$columns}) VALUES\n".implode(",\n", $rows).";\n");
    }

    private function literal(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        $text = (string) $value;

        // Binary-safe: anything that is not valid UTF-8 is written as hex.
        if ($text !== '' && ! mb_check_encoding($text, 'UTF-8')) {
            return '0x'.bin2hex($text);
        }

        return $pdo->quote($text);
    }

    private function identifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    /**
     * @param  resource  $handle
     */
    private function write($handle, string $chunk): void
    {
        if (fwrite($handle, $chunk) === false) {
            throw new RuntimeException('Unable to write to the backup file (disk full or not writable).');
        }
    }
}
