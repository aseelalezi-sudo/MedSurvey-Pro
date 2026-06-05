<?php

use App\Models\Settings;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune')->daily();

try {
    $settings = Settings::query()->where('id', 'global')->first();
    $defaults = app(SettingsService::class)->defaults();
    $backupSettings = $settings?->data['backupSettings'] ?? $defaults['backupSettings'];
    $archiveSettings = $settings?->data['archiveSettings'] ?? $defaults['archiveSettings'];

    $scheduleTime = $backupSettings['schedule'] ?? '03:00';

    Schedule::command('backup:run')->dailyAt($scheduleTime);

    if ((bool) ($archiveSettings['enabled'] ?? true)) {
        $archiveYears = max(1, (int) ($archiveSettings['retentionYears'] ?? 3));
        $archiveTime = $archiveSettings['schedule'] ?? '02:30';

        Schedule::command("archive:old-data --years={$archiveYears}")
            ->dailyAt($archiveTime)
            ->withoutOverlapping();
    }
} catch (Exception $e) {
    // Fallback if DB is not ready
    Schedule::command('archive:old-data --years=3')->dailyAt('02:30')->withoutOverlapping();
    Schedule::command('backup:run')->dailyAt('03:00');
}
