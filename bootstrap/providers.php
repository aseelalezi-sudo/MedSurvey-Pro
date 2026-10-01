<?php

use App\Licensing\LicenseServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;

return [
    AppServiceProvider::class,
    LicenseServiceProvider::class,
    EventServiceProvider::class,
];
