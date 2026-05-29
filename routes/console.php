<?php

use App\Services\SettingsService;
use App\Models\Settings;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune')->daily();
Schedule::command('archive:old-data')->dailyAt('02:30')->withoutOverlapping();

try {
    $settings = Settings::query()->where('id', 'global')->first();
    $defaults = app(SettingsService::class)->defaults()['backupSettings'];
    $backupSettings = $settings?->data['backupSettings'] ?? $defaults;

    $scheduleTime = $backupSettings['schedule'] ?? '03:00';

    Schedule::command('backup:run')->dailyAt($scheduleTime);
} catch (Exception $e) {
    // Fallback if DB is not ready
    Schedule::command('backup:run')->dailyAt('03:00');
}
