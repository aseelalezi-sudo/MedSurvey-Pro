<?php

return [
    'driver' => env('SESSION_DRIVER', 'file'),
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),
    'encrypt' => env('SESSION_ENCRYPT', true),
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => env('SESSION_TABLE', 'sessions'),
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', 'medsurvey_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    // When secure is null, Symfony Cookie auto-detects based on whether the request is HTTPS or HTTP.
    // This allows dual-access (HTTP via local LAN IP and HTTPS via Cloudflare) without dropped session cookies.
    'secure' => env('SESSION_SECURE_COOKIE', null),
    'http_only' => true,
    'same_site' => env('SESSION_SAME_SITE', 'lax'),
];
