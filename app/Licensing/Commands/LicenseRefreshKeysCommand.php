<?php

namespace App\Licensing\Commands;

use App\Licensing\Services\LicenseService;
use Illuminate\Console\Command;

class LicenseRefreshKeysCommand extends Command
{
    protected $signature = 'license:refresh-keys';

    protected $description = 'Fetch and cache the latest public signing keys from the server';

    public function handle(LicenseService $licensing): int
    {
        if ($licensing->refreshKeys()) {
            $this->components->info('Public signing keys refreshed.');

            return self::SUCCESS;
        }

        $this->components->warn('Could not refresh public keys (server unreachable or empty response).');

        return self::SUCCESS;
    }
}
