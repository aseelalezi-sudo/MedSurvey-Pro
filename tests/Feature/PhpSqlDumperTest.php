<?php

namespace Tests\Feature;

use App\Services\BackupService;
use App\Services\PhpSqlDumper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhpSqlDumperTest extends TestCase
{
    private string $table = 'zz_dump_probe';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('framework/testing/backups_'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->dir);
        Schema::dropIfExists($this->table);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists($this->table);
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    public function test_dump_round_trips_awkward_values(): void
    {
        DB::statement("CREATE TABLE {$this->table} (id INT PRIMARY KEY, note TEXT NULL, blob_col BLOB NULL, amount DECIMAL(10,2) NULL, flag TINYINT(1) NOT NULL DEFAULT 0)");

        $rows = [
            ['id' => 1, 'note' => "O'Brien said \"hi\"\nsecond line \ backslash", 'blob_col' => "\x00\x01\xff", 'amount' => '12.50', 'flag' => 1],
            ['id' => 2, 'note' => 'مرحبا بالعالم — نص عربي 😀', 'blob_col' => null, 'amount' => null, 'flag' => 0],
            ['id' => 3, 'note' => null, 'blob_col' => '', 'amount' => '0.00', 'flag' => 0],
        ];
        DB::table($this->table)->insert($rows);
        $before = DB::table($this->table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        $path = $this->dir.'/probe.sql';
        (new PhpSqlDumper)->dump($path, [$this->table]);
        $sql = File::get($path);

        $this->assertStringContainsString('CREATE TABLE `'.$this->table.'`', $sql);
        $this->assertStringContainsString('-- Dump completed', $sql);

        Schema::drop($this->table);
        DB::unprepared($sql);

        $after = DB::table($this->table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $this->assertSame($before, $after);
    }

    public function test_backup_falls_back_to_the_php_dumper_when_mysqldump_is_missing(): void
    {
        config([
            'medsurvey.backup.mysqldump_path' => $this->dir.'/no-such-mysqldump',
            'medsurvey.backup.backup_dir' => $this->dir,
        ]);

        $result = app(BackupService::class)->create();

        $this->assertFileExists($result['file']);
        $this->assertGreaterThan(0, File::size($result['file']));
        $this->assertTrue($result['verification']['valid'] ?? false, 'the fallback dump must pass verification');
        $this->assertGreaterThan(5, $result['verification']['tableCount']);
    }
}
