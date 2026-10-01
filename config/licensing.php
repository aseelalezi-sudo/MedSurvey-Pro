<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    | Set to false (e.g. during development) to bypass all licensing checks.
    | The RequiresValidLicense middleware and LicenseService::boot() both
    | respect this flag.
    */
    'enabled' => (bool) env('LICENSING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | LicenseHub server
    |--------------------------------------------------------------------------
    | base URL of the license server (no trailing slash), the product slug that
    | this installation corresponds to on that server, and the license key that
    | was sold to this customer.
    */
    'server' => env('LICENSING_SERVER_URL', ''),
    'product' => env('LICENSING_PRODUCT', 'medsurvey-pro'),
    'key' => env('LICENSING_LICENSE_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Token issuer
    |--------------------------------------------------------------------------
    | Must match LicenseHub's LICENSE_TOKEN_ISSUER so that the SDK can reject
    | tokens minted by a different server.
    */
    'issuer' => env('LICENSING_ISSUER', 'licensehub'),

    /*
    |--------------------------------------------------------------------------
    | Device binding secret
    |--------------------------------------------------------------------------
    | MUST equal LicenseHub's LICENSE_DEVICE_HASH_SECRET. It is used to derive
    | the device fingerprint hash (devf) embedded in signed tokens, so the SDK
    | can bind a token to this installation. If unset we fall back to APP_KEY,
    | but for production set it explicitly.
    */
    'device_hash_secret' => env('LICENSING_DEVICE_HASH_SECRET', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Client identity reported to LicenseHub on every API call.
    */
    'client_name' => env('LICENSING_CLIENT_NAME', 'medsurvey-sdk'),
    'client_version' => env('LICENSING_CLIENT_VERSION', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Network
    |--------------------------------------------------------------------------
    | HTTP timeout in seconds and how long cached public keys live before they
    | are refreshed from the server.
    */
    'timeout' => (int) env('LICENSING_TIMEOUT', 5),
    'public_key_cache_ttl' => (int) env('LICENSING_PUBLIC_KEY_TTL', 86400),

    /*
    |--------------------------------------------------------------------------
    | Heartbeat period
    |--------------------------------------------------------------------------
    | How often (in minutes) the SDK should re-check the license online so that
    | revocations are picked up. Used by the heartbeat command / scheduler.
    */
    'heartbeat_interval' => (int) env('LICENSING_HEARTBEAT_INTERVAL', 60),

];
