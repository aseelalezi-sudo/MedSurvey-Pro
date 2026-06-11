<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use App\Services\SettingsService;
use Illuminate\Console\Command;

class RunBackup extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Run the database backup and cleanup old backups based on settings';

    public function handle(BackupService $backupService, SettingsService $settingsService): int
    {
        $this->info('Starting automated database backup...');

        try {
            $backupService->create();
        } catch (\Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup created successfully.');

        $this->cleanupOldBackups($settingsService);

        return self::SUCCESS;
    }

    private function cleanupOldBackups(SettingsService $settingsService): void
    {
        $settings = $settingsService->getAll(null);
        $backupSettings = $settings['backupSettings'] ?? $settingsService->defaults()['backupSettings'];

        $retentionDays = (int) ($backupSettings['retentionDays'] ?? 30);
        $dir = $backupSettings['backupDir'] ?? 'storage/app/backups';

        $backupDir = str_starts_with($dir, '/') || preg_match('/^[a-zA-Z]:\\\\/', $dir)
            ? $dir
            : base_path(trim($dir, '/\\'));

        if (! is_dir($backupDir)) {
            return;
        }

        $threshold = now()->subDays($retentionDays)->getTimestamp();
        $files = array_merge(
            glob($backupDir.DIRECTORY_SEPARATOR.'*.sql') ?: [],
            glob($backupDir.DIRECTORY_SEPARATOR.'*.sql.gz') ?: []
        );
        $deletedCount = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                unlink($file);
                $deletedCount++;
                $this->info('Deleted old backup: '.basename($file));
            }
        }

        if ($deletedCount > 0) {
            $this->info("Cleanup complete. Deleted {$deletedCount} old backup(s).");
        } else {
            $this->info("No backups older than {$retentionDays} days found to delete.");
        }
    }
}
