<?php

namespace App\Licensing\Facades;

use App\Licensing\Services\LicenseService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Licensing\Support\LicenseCheck boot()
 * @method static \App\Licensing\Support\LicenseCheck activate()
 * @method static \App\Licensing\Support\LicenseCheck validate()
 * @method static \App\Licensing\Support\LicenseCheck heartbeat()
 * @method static void deactivate()
 * @method static bool refreshKeys()
 * @method static \App\Licensing\Models\LicenseState state()
 */
class License extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LicenseService::class;
    }
}
