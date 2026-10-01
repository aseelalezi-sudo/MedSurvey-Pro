<?php

namespace App\Licensing\Commands;

use App\Licensing\Services\LicenseService;
use Illuminate\Console\Command;

class LicenseValidateCommand extends Command
{
    protected $signature = 'license:validate';

    protected $description = 'Re-validate the license online and refresh the signed token';

    public function handle(LicenseService $licensing): int
    {
        $check = $licensing->validate();

        if ($check->valid()) {
            $this->components->info('License is valid.');

            return self::SUCCESS;
        }

        $this->components->error('License invalid: '.$check->reason);

        return self::FAILURE;
    }
}
