<?php

use App\Models\Settings;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

// Only the command name and completion time enter the operational log.
$success = fn (string $name) => fn () => Log::info('scheduler.success', ['task' => $name]);
Schedule::command('model:prune')->daily()->onSuccess($success('model:prune'));

try {
    $settings = Settings::query()->where('id', 'global')->first();
    $defaults = app(SettingsService::class)->defaults();
    $backupSettings = $settings?->data['backupSettings'] ?? $defaults['backupSettings'];
    $archiveSettings = $settings?->data['archiveSettings'] ?? $defaults['archiveSettings'];

    $scheduleTime = $backupSettings['schedule'] ?? '03:00';

    $archiveEnabled = (bool) ($archiveSettings['enabled'] ?? true);
    $archiveYears = max(1, (int) ($archiveSettings['retentionYears'] ?? 3));
    $archiveTime = $archiveSettings['schedule'] ?? '02:30';
} catch (Exception $e) {
    $scheduleTime = '03:00';
    $archiveEnabled = true;
    $archiveYears = 3;
    $archiveTime = '02:30';
}
Schedule::command('backup:run')->dailyAt($scheduleTime)->withoutOverlapping()->onSuccess($success('backup:run'));
if ($archiveEnabled) {
    Schedule::command("archive:old-data --years={$archiveYears}")
        ->dailyAt($archiveTime)->withoutOverlapping()->onSuccess($success('archive:old-data'));
}
