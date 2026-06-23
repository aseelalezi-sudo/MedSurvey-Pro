<?php

namespace Tests\Feature;

use App\Services\BackupService;
use Mockery\MockInterface;
use Tests\TestCase;

class BackupRestoreCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_it_aborts_when_restore_is_disabled()
    {
        config(['medsurvey.backup.server_restore_enabled' => false]);

        $this->artisan('backup:restore', [
            'backupFile' => 'test.sql.gz',
            '--force' => true,
            '--secret' => 'correct-secret',
        ])
            ->expectsOutput('Database restoration is disabled. Set BACKUP_RESTORE_ENABLED=true to enable.')
            ->assertFailed();
    }

    public function test_it_aborts_when_force_flag_is_missing()
    {
        config(['medsurvey.backup.server_restore_enabled' => true]);

        $this->artisan('backup:restore', [
            'backupFile' => 'test.sql.gz',
            '--secret' => 'correct-secret',
        ])
            ->expectsOutput('The --force flag is required to restore a backup. This action will overwrite the current database.')
            ->assertFailed();
    }

    public function test_it_aborts_when_secret_is_missing_or_incorrect()
    {
        config(['medsurvey.backup.server_restore_enabled' => true]);
        config(['medsurvey.backup.server_restore_secret' => 'correct-secret']);

        $this->artisan('backup:restore', [
            'backupFile' => 'test.sql.gz',
            '--force' => true,
            '--secret' => 'wrong-secret',
        ])
            ->expectsOutput('Invalid or missing --secret. The correct secret key must be provided.')
            ->assertFailed();

        $this->artisan('backup:restore', [
            'backupFile' => 'test.sql.gz',
            '--force' => true,
        ])
            ->expectsOutput('Invalid or missing --secret. The correct secret key must be provided.')
            ->assertFailed();
    }

    public function test_it_aborts_when_verification_fails()
    {
        config(['medsurvey.backup.server_restore_enabled' => true]);
        config(['medsurvey.backup.server_restore_secret' => 'correct-secret']);

        $this->mock(BackupService::class, function (MockInterface $mock) {
            $mock->shouldReceive('verifyExternalPath')->with('test.sql.gz')->andReturn('/fake/path/test.sql.gz');
            $mock->shouldReceive('verifyPath')->with('/fake/path/test.sql.gz')->andReturn([
                'valid' => false,
                'error' => 'File corrupted',
            ]);
        });

        $this->artisan('backup:restore', [
            'backupFile' => 'test.sql.gz',
            '--force' => true,
            '--secret' => 'correct-secret',
        ])
            ->expectsOutput('Backup file verification failed: File corrupted')
            ->assertFailed();
    }

    public function test_it_executes_restore_successfully_and_logs_action()
    {
        config(['medsurvey.backup.server_restore_enabled' => true]);
        config(['medsurvey.backup.server_restore_secret' => 'correct-secret']);

        $this->mock(BackupService::class, function (MockInterface $mock) {
            $mock->shouldReceive('verifyExternalPath')->with('test.sql.gz')->andReturn('/fake/path/test.sql.gz');
            $mock->shouldReceive('verifyPath')->with('/fake/path/test.sql.gz')->andReturn(['valid' => true]);
            $mock->shouldReceive('restore')->with('/fake/path/test.sql.gz')->once();
        });

        $this->artisan('backup:restore', [
            'backupFile' => 'test.sql.gz',
            '--force' => true,
            '--secret' => 'correct-secret',
        ])
            ->expectsOutput('Database restored successfully from test.sql.gz.')
            ->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'server_backup_restore',
            'ipAddress' => '127.0.0.1',
            'userAgent' => 'CLI',
            'deviceName' => 'Server',
        ]);
    }
}
