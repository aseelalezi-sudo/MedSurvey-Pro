<?php

namespace App\Licensing\Commands;

use App\Licensing\Services\LicenseService;
use Illuminate\Console\Command;

class LicenseDeactivateCommand extends Command
{
    protected $signature = 'license:deactivate';

    protected $description = 'Free the device seat and clear local licensing state';

    public function handle(LicenseService $licensing): int
    {
        $licensing->deactivate();

        $this->components->info('License deactivated and local state cleared.');

        return self::SUCCESS;
    }
}
