<?php

namespace App\Licensing\Commands;

use App\Licensing\Services\LicenseService;
use Illuminate\Console\Command;

class LicenseHeartbeatCommand extends Command
{
    protected $signature = 'license:heartbeat';

    protected $description = 'Send a periodic online heartbeat to check for revocation';

    public function handle(LicenseService $licensing): int
    {
        $check = $licensing->heartbeat();

        if ($check->valid()) {
            $this->components->info('Heartbeat ok; license is still valid.');

            return self::SUCCESS;
        }

        if ($check->status->value === 'error') {
            $this->components->warn('Heartbeat unreachable; license presumed valid offline.');

            return self::SUCCESS;
        }

        $this->components->error('Heartbeat failed: '.$check->reason);

        return self::FAILURE;
    }
}
