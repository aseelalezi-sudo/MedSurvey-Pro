<?php

// Invoke only from mysql-native-roundtrip.py, which provides a new, isolated server.
use App\Licensing\Models\LicenseState;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): never {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
});

function ensure(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

ensure($app->environment('testing') && DB::connection()->getDatabaseName() === 'schema_cycle_test', 'Unsafe schema fixture');
ensure(DB::selectOne('SELECT @@server_uuid AS id')->id === getenv('FIXTURE_MYSQL_UUID'), 'Wrong fixture server');
ensure(DB::selectOne('SELECT VERSION() AS version')->version === getenv('DB_EXPECTED_MYSQL_VERSION'), 'Wrong fixture version');

function migrate(string $command, array $options = []): void
{
    ensure(Artisan::call($command, ['--force' => true] + $options) === 0, 'Failed '.$command);
}

$production = array_filter(array_map('basename', glob(database_path('migrations/*.php'))), fn ($path) => $path < '2026_11_28');
$payloadMigration = 'database/migrations/2026_11_28_000001_store_encrypted_license_payload_as_text.php';
migrate('migrate', ['--path' => array_map(fn ($p) => 'database/migrations/'.$p, $production)]);
ensure(Schema::getColumnType('license_states', 'payload') === 'json', 'Unexpected original schema');

$claimed = ['features' => ['reports'], 'fixture' => 'industrial-test'];
$encrypted = Crypt::encryptString(json_encode($claimed, JSON_THROW_ON_ERROR));
DB::table('license_states')->insert([
    ['id' => 'null', 'product' => 'missing', 'payload' => null],
    ['id' => 'json-null', 'product' => 'json-null', 'payload' => 'null'],
    ['id' => 'wrapped', 'product' => 'wrapped', 'payload' => json_encode($encrypted, JSON_THROW_ON_ERROR)],
    ['id' => 'structured', 'product' => 'structured', 'payload' => json_encode($claimed, JSON_THROW_ON_ERROR)],
    ['id' => 'bad', 'product' => 'bad', 'payload' => '"invalid-encrypted-fixture"'],
]);
try {
    migrate('migrate', ['--path' => [$payloadMigration]]);
    throw new LogicException('Failed to reject an unreadable legacy payload');
} catch (RuntimeException $error) {
    ensure(str_contains($error->getMessage(), 'legacy license payload'), 'Unexpected migration error');
}
ensure(Schema::getColumnType('license_states', 'payload') === 'json', 'Invalid input changed schema');
DB::table('license_states')->where('id', 'bad')->delete();
DB::table('tenants')->insert(['id' => 'keep', 'name' => 'Keep']);
DB::table('surveys')->insert(['id' => 'keep-survey', 'title' => 'Keep', 'description' => 'test', 'tenantId' => 'keep']);
DB::table('survey_responses')->insert([
    'id' => 'keep-response', 'surveyId' => 'keep-survey', 'tenantId' => 'keep',
    'department' => 'test', 'answers' => '[]', 'overallScore' => 75,
]);

migrate('migrate', ['--path' => [$payloadMigration]]);
ensure(Schema::getColumnType('license_states', 'payload') === 'longtext', 'Ciphertext column is not longtext');
foreach (['wrapped', 'structured'] as $id) {
    $stored = LicenseState::findOrFail($id)->payload;
    ksort($stored);
    ensure($stored === $claimed, 'Old claims changed');
}
ensure(DB::table('license_states')->where('id', 'wrapped')->value('payload') === $encrypted, 'Ciphertext changed');
ensure(DB::table('license_states')->whereIn('id', ['null', 'json-null'])->whereNotNull('payload')->count() === 0, 'Null handling failed');
ensure(DB::table('survey_responses')->where('tenantId', 'keep')->where('surveyId', 'keep-survey')->count() === 1, 'Tenant ownership changed');
ensure(Schema::getForeignKeys('survey_responses') !== [], 'Foreign keys were removed');

$state = LicenseState::query()->create(['product' => 'new', 'payload' => $claimed]);
ensure($state->fresh()->payload === $claimed && ! str_contains($state->getRawOriginal('payload'), 'reports'), 'New claims did not remain encrypted');
migrate('migrate:rollback', ['--step' => 1]);
ensure(LicenseState::findOrFail($state->id)->payload === $claimed, 'Backward code compatibility failed');
migrate('migrate', ['--path' => [$payloadMigration]]);
ensure(LicenseState::findOrFail($state->id)->getRawOriginal('payload') === $state->getRawOriginal('payload'), 'Reapplication changed raw ciphertext');

migrate('migrate:reset');
ensure(DB::table('migrations')->count() === 0, 'Reset left migrations applied');
migrate('migrate');
migrate('migrate:rollback');
ensure(DB::table('migrations')->count() === 0, 'Rollback left migrations applied');
migrate('migrate');
ensure(DB::table('migrations')->count() === count($production) + 1, 'Reapplication left migrations pending');
echo "PASS: production-shaped schema, legacy payloads, tenant row and full up/down/up\n";
