<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\SettingsController;
use App\Models\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RunBackup extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Run the database backup and cleanup old backups based on settings';

    public function handle()
    {
        $this->info('Starting automated database backup...');

        // 1. Create the backup
        $controller = new BackupController;
        $response = $controller->create();

        if ($response->getStatusCode() !== 200) {
            $this->error('Backup failed: '.json_encode($response->getData(true)));

            return self::FAILURE;
        }

        $this->info('Backup created successfully.');

        // 2. Cleanup old backups
        $this->cleanupOldBackups();

        return self::SUCCESS;
    }

    private function cleanupOldBackups()
    {
        $settings = Settings::query()->where('id', 'global')->first();
        $defaults = (new SettingsController)->defaults()['backupSettings'];
        $backupSettings = $settings?->data['backupSettings'] ?? $defaults;

        $retentionDays = (int) ($backupSettings['retentionDays'] ?? 30);
        $dir = $backupSettings['backupDir'] ?? 'storage/app/backups';

        $backupDir = str_starts_with($dir, '/') || preg_match('/^[a-zA-Z]:\\\\/', $dir)
            ? $dir
            : base_path(trim($dir, '/\\'));

        if (! File::exists($backupDir)) {
            return;
        }

        $threshold = now()->subDays($retentionDays)->getTimestamp();
        $files = File::files($backupDir);
        $deletedCount = 0;

        foreach ($files as $file) {
            if (str_ends_with($file->getFilename(), '.sql') || str_ends_with($file->getFilename(), '.sql.gz')) {
                if ($file->getMTime() < $threshold) {
                    File::delete($file->getPathname());
                    $deletedCount++;
                    $this->info('Deleted old backup: '.$file->getFilename());
                }
            }
        }

        if ($deletedCount > 0) {
            $this->info("Cleanup complete. Deleted {$deletedCount} old backup(s).");
        } else {
            $this->info("No backups older than {$retentionDays} days found to delete.");
        }
    }
}
