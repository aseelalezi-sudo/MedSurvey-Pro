<?php

// Preserve the installed Reverb version's server and scaling options.
$config = require base_path('vendor/laravel/reverb/config/reverb.php');
$hosts = env('REVERB_ALLOWED_ORIGINS', parse_url(config('app.url', env('APP_URL', 'http://localhost')), PHP_URL_HOST) ?: 'localhost');
$config['apps']['apps'][0]['allowed_origins'] = array_values(array_filter(array_map('trim', explode(',', $hosts))));

return $config;
