<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RestoreBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:restore {backupFile} {--force} {--secret=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore a database backup from the local server directory securely.';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $backupFile = $this->argument('backupFile');
        $force = $this->option('force');
        $secret = $this->option('secret');

        if (! config('medsurvey.backup.server_restore_enabled')) {
            $this->error('Database restoration is disabled. Set BACKUP_RESTORE_ENABLED=true to enable.');

            return self::FAILURE;
        }

        if (! $force) {
            $this->error('The --force flag is required to restore a backup. This action will overwrite the current database.');

            return self::FAILURE;
        }

        $expectedSecret = config('medsurvey.backup.server_restore_secret');
        if (empty($expectedSecret) || $secret !== $expectedSecret) {
            $this->error('Invalid or missing --secret. The correct secret key must be provided.');

            return self::FAILURE;
        }

        $this->info("Verifying backup file: {$backupFile}...");

        try {
            $path = $backupService->verifyExternalPath($backupFile);
            $verification = $backupService->verifyPath($path);

            if (! $verification['valid']) {
                $this->error('Backup file verification failed: '.($verification['error'] ?? 'Unknown error.'));

                return self::FAILURE;
            }

            $this->info('Backup verified successfully.');
            $this->info('Putting application into maintenance mode...');
            Artisan::call('down', ['--render' => 'errors::503']);

            $this->info('Restoring database. This may take a while...');
            $backupService->restore($path);

            $this->info('Restoration completed.');

            // Clear caches
            $this->info('Clearing caches...');
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');

            $this->info('Bringing application back online...');
            Artisan::call('up');

            // Log the action
            $this->logRestoreAction($backupFile);

            $this->info("Database restored successfully from {$backupFile}.");

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Restoration failed: '.$e->getMessage());

            // Ensure app is up if it failed during restore
            if (file_exists(storage_path('framework/down'))) {
                $this->info('Attempting to bring application back online after failure...');
                Artisan::call('up');
            }

            return self::FAILURE;
        }
    }

    private function logRestoreAction(string $backupFile): void
    {
        try {
            DB::table('audit_logs')->insert([
                'id' => Str::ulid()->toString(),
                'userId' => null, // CLI command, no specific user
                'action' => 'server_backup_restore',
                'details' => json_encode([
                    'messageKey' => 'audit.details.server_backup_restore',
                    'params' => [
                        'filename' => $backupFile,
                        'source' => 'cli',
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'ipAddress' => '127.0.0.1',
                'userAgent' => 'CLI',
                'deviceName' => 'Server',
                'timestamp' => now(),
            ]);
            Log::info("Database restored from CLI using file: {$backupFile}");
        } catch (Exception $e) {
            Log::error('Failed to write audit log for CLI restore: '.$e->getMessage());
        }
    }
}
