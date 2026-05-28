<?php

return [
    'public_tenant_id' => env('PUBLIC_TENANT_ID', ''),

    'backup' => [
        'restore_enabled' => (bool) env('DB_BACKUP_RESTORE_ENABLED', false),
        'retention_days' => (int) env('DB_BACKUP_RETENTION_DAYS', 30),
        'mysqldump_path' => env('MYSQLDUMP_PATH'),
        'mysql_path' => env('MYSQL_PATH'),
    ],
];
