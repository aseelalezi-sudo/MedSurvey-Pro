<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @author   Taylor Otwell <taylor@laravel.com>
 */
$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// This file allows us to emulate Apache's "mod_rewrite" functionality from the
// built-in PHP web server. This provides a convenient way to test a Laravel
// application without having installed a "real" web server software here.
if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

foreach (['APP_ENV', 'DB_DATABASE', 'DB_CONNECTION', 'LICENSING_ENABLED'] as $envKey) {
    $envVal = getenv($envKey);
    if ($envVal !== false) {
        $_SERVER[$envKey] = $envVal;
        $_ENV[$envKey] = $envVal;
    }
}

require_once __DIR__.'/public/index.php';
