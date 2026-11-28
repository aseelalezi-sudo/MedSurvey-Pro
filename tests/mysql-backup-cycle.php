<?php

// Invoke only from mysql-native-roundtrip.py.
use App\Services\BackupService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): never {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
});

function requireFixture(string $name): void
{
    if (DB::selectOne('SELECT DATABASE() AS db, @@server_uuid AS uuid')->db !== $name
        || DB::selectOne('SELECT @@server_uuid AS uuid')->uuid !== getenv('FIXTURE_MYSQL_UUID')) {
        throw new RuntimeException('Unsafe restore fixture.');
    }
}

requireFixture('schema_cycle_test');
DB::table('tenants')->insert(['id' => 'probe', 'name' => 'Backup integrity']);
$backupDir = getenv('FIXTURE_TMP');
config(['medsurvey.backup.backup_dir' => $backupDir]);
$service = app(BackupService::class);
$native = config('medsurvey.backup.mysqldump_path');
config(['medsurvey.backup.mysqldump_path' => getenv('FIXTURE_INCOMPATIBLE')]);
try {
    $service->create();
    throw new LogicException('Accepted an incompatible dump client.');
} catch (RuntimeException $error) {
    if (! str_contains($error->getMessage(), 'same MySQL')) {
        throw $error;
    }
}
config(['medsurvey.backup.mysqldump_path' => $native]);
$backup = $service->create();
$path = $backup['file'];
if (! $backup['verification']['valid'] || ! $service->verifyPath($path)['checksumVerified']) {
    throw new RuntimeException('Native backup or manifest verification failed.');
}

config(['database.connections.mysql.database' => 'restore_cycle_test']);
DB::purge();
requireFixture('restore_cycle_test');
$invalidPath = $backupDir.'/tampered.sql';
file_put_contents($invalidPath, 'CREATE TABLE fixture_tamper (id INT);');
copy($path.'.sha256', $invalidPath.'.sha256');
try {
    app(BackupService::class)->restore($invalidPath);
    throw new LogicException('Accepted an incorrect digest.');
} catch (RuntimeException $error) {
    if (! str_contains($error->getMessage(), 'SHA-256')) {
        throw $error;
    }
}

app(BackupService::class)->restore($path);
if (DB::table('tenants')->where('id', 'probe')->value('name') !== 'Backup integrity') {
    throw new RuntimeException('Restore did not recover the fixture tenant.');
}
echo "PASS: native backup, incompatible client refusal, SHA-256 mismatch refusal, isolated restore\n";
