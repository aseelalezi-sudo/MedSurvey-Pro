<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Services\BackupService;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RunBackupCommandTest extends TestCase
{
    public function test_backup_run_uses_backup_service_and_exits_successfully(): void
    {
        $backupDir = storage_path('framework/testing/backups-'.bin2hex(random_bytes(4)));

        File::ensureDirectoryExists($backupDir);

        Settings::query()->updateOrCreate(
            ['id' => 'global'],
            [
                'tenantId' => null,
                'data' => array_replace_recursive(app(SettingsService::class)->defaults(), [
                    'backupSettings' => [
                        'schedule' => '03:00',
                        'retentionDays' => 30,
                        'compressGzip' => true,
                        'backupDir' => $backupDir,
                    ],
                ]),
            ]
        );

        $this->mock(BackupService::class, function ($mock): void {
            $mock->shouldReceive('create')
                ->once()
                ->andReturn([
                    'message' => 'Backup created successfully',
                    'file' => 'fake.sql.gz',
                    'timestamp' => now()->toISOString(),
                ]);
        });

        try {
            $this->artisan('backup:run')
                ->expectsOutput('Starting automated database backup...')
                ->expectsOutput('Backup created successfully.')
                ->assertExitCode(Command::SUCCESS);
        } finally {
            File::deleteDirectory($backupDir);
        }
    }

    public function test_backup_run_returns_failure_when_backup_service_fails(): void
    {
        $this->mock(BackupService::class, function ($mock): void {
            $mock->shouldReceive('create')
                ->once()
                ->andThrow(new \RuntimeException('mysqldump failed'));
        });

        $this->artisan('backup:run')
            ->expectsOutput('Starting automated database backup...')
            ->expectsOutput('Backup failed: mysqldump failed')
            ->assertExitCode(Command::FAILURE);
    }
}
