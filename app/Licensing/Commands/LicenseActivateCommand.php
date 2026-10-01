<?php

namespace App\Licensing\Commands;

use App\Licensing\Services\LicenseService;
use Illuminate\Console\Command;

class LicenseActivateCommand extends Command
{
    protected $signature = 'license:activate {--key= : The license key to activate (defaults to config)}';

    protected $description = 'Activate this installation online against the license server';

    public function handle(LicenseService $licensing): int
    {
        if ($key = $this->option('key')) {
            config(['licensing.key' => $key]);
        }

        $check = $licensing->activate();

        if ($check->valid()) {
            $this->components->info('License activated successfully.');

            if ($check->expiresAt() !== null) {
                $this->line('Expires: '.$check->expiresAt());
            }

            return self::SUCCESS;
        }

        $this->components->error('Activation failed: '.$check->reason);

        return self::FAILURE;
    }
}
