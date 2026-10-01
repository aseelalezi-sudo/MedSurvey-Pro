<?php

namespace App\Licensing\Commands;

use App\Licensing\Services\LicenseService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class LicenseStatusCommand extends Command
{
    protected $signature = 'license:status {--verbose : Show persisted state fields}';

    protected $description = 'Show the current licensing status of this installation';

    public function handle(LicenseService $licensing): int
    {
        $check = $licensing->boot();

        $this->components->info(sprintf('Product : %s', config('licensing.product', 'medsurvey-pro')));

        $status = ucwords(str_replace('_', ' ', $check->status->value));

        if ($check->valid()) {
            $this->info("Status  : {$status}");

            if ($check->type() !== null) {
                $this->line(sprintf('Type    : %s', $check->type()));
                $this->line(sprintf('License : %s', $check->licenseUuid() ?? '-'));
            }

            if ($check->expiresAt() !== null) {
                $this->line(sprintf('Expires : %s', $check->expiresAt()));
            }

            if ($features = $check->features()) {
                $this->line('Features: '.implode(', ', $features));
            }
        } else {
            $this->components->error("Status  : {$status}");
            $this->line("Reason  : {$check->reason}");
        }

        if ($this->option('verbose')) {
            $state = $licensing->state();
            $this->newLine();
            $this->line('State   - '.(is_string($state->license_key) ? Str::mask($state->license_key, 4, 6, '*') : '(not configured)'));
            $this->line('State   - token: '.($state->token ? 'yes' : 'no').', keys: '.(is_array($state->public_keys) ? count($state->public_keys) : 0).', heartbeat: '.($state->last_heartbeat_at?->toDateTimeString() ?? 'never'));
        }

        return $check->valid() ? self::SUCCESS : self::FAILURE;
    }
}
