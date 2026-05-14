<?php
/*
 * Container-friendly local config — committed to the repo.
 *
 * Reads everything from env vars so that each Coolify deployment (main dev
 * branch and per-PR previews) gets its own connection string without any
 * code changes.
 *
 * Required env vars (set in Coolify UI; mark passwords as secret):
 *   DB_HOST DB_PORT DB_NAME DB_USER DB_PASSWORD
 *   REDIS_HOST REDIS_PORT REDIS_PASSWORD
 *   SECURITY_SALT DEBUG
 *
 * SMTP is opt-in per app; uncomment the EmailTransport block when needed.
 */

use function Cake\Core\env;

return [
    'debug' => filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN),

    'Security' => [
        'salt' => env('SECURITY_SALT', '__SALT__'),
    ],

    'Datasources' => [
        'default' => [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Mysql',
            'persistent' => false,
            'host' => env('DB_HOST', 'db'),
            'port' => env('DB_PORT', '3306'),
            'username' => env('DB_USER', 'app'),
            'password' => env('DB_PASSWORD', ''),
            'database' => env('DB_NAME', 'app'),
            'encoding' => 'utf8mb4',
            'timezone' => 'UTC',
            'flags' => [],
            'cacheMetadata' => true,
            'log' => false,
            'quoteIdentifiers' => false,
            'url' => env('DATABASE_URL', null),
        ],

        'test' => [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Mysql',
            'persistent' => false,
            'host' => env('DB_HOST', 'db'),
            'port' => env('DB_PORT', '3306'),
            'username' => env('DB_USER', 'app'),
            'password' => env('DB_PASSWORD', ''),
            'database' => env('DB_TEST_NAME', 'app_test'),
            'encoding' => 'utf8mb4',
            'timezone' => 'UTC',
            'cacheMetadata' => true,
            'quoteIdentifiers' => false,
            'url' => env('DATABASE_TEST_URL', null),
        ],
    ],

    // Uncomment + configure when the app needs to send mail.
    // Recommended: use SMTP via Coolify-supplied env vars.
    // 'EmailTransport' => [
    //     'default' => [
    //         'className' => 'Smtp',
    //         'host' => env('SMTP_HOST', 'localhost'),
    //         'port' => (int)env('SMTP_PORT', 25),
    //         'username' => env('SMTP_USER', null),
    //         'password' => env('SMTP_PASSWORD', null),
    //         'client' => null,
    //         'url' => env('EMAIL_TRANSPORT_DEFAULT_URL', null),
    //     ],
    // ],
];
