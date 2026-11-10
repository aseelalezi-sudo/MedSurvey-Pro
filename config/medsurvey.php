<?php

return [
    'public_tenant_id' => env('PUBLIC_TENANT_ID', ''),
    'allow_public_tenant_query' => (bool) env('PUBLIC_TENANT_QUERY_ALLOWED', false),

    // Sends Strict-Transport-Security on HTTPS responses. Leave off when
    // Cloudflare (or another edge) already sets it, to avoid duplicate headers.
    'hsts' => (bool) env('SECURITY_HSTS', false),

    'backup' => [
        'restore_enabled' => (bool) env('DB_BACKUP_RESTORE_ENABLED', false),
        'retention_days' => (int) env('DB_BACKUP_RETENTION_DAYS', 30),
        'backup_dir' => env('DB_BACKUP_DIR', 'storage/app/backups'),
        'mysqldump_path' => env('MYSQLDUMP_PATH'),
        'mysql_path' => env('MYSQL_PATH'),
        'server_restore_enabled' => (bool) env('BACKUP_RESTORE_ENABLED', false),
        'server_restore_secret' => env('BACKUP_RESTORE_SECRET', ''),
    ],

    // Latest Android build offered to collectors. The app compares its own
    // build number with `latest_build` and offers an update when this one is
    // newer. An empty `download_url` disables the notice entirely, so a
    // deployment that distributes APKs by hand is unaffected.
    'mobile' => [
        'latest_version' => env('MOBILE_LATEST_VERSION', ''),
        'latest_build' => (int) env('MOBILE_LATEST_BUILD', 0),
        'download_url' => env('MOBILE_DOWNLOAD_URL', ''),
        'notes' => env('MOBILE_UPDATE_NOTES', ''),
        'min_build' => (int) env('MOBILE_MIN_BUILD', 0),
    ],
];
