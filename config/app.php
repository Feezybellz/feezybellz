<?php

/**
 * Application Configuration
 */

return [
    'name' => env('APP_NAME', 'Framework Application'),
    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'jwt_secret' => env('JWT_SECRET', ''),
    'google_map_api_key' => env('GOOGLE_MAP_API_KEY', ''),
    'ws_url' => env('WS_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | WebSocket Configuration
    |--------------------------------------------------------------------------
    */
    'websocket' => [
        'port'          => env('WS_PORT', 8080),
        'internal_port' => env('WS_INTERNAL_PORT', 8081),
        'host'          => env('WS_HOST', '0.0.0.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Wallet Configuration
    |--------------------------------------------------------------------------
    */
    'wallet' => [
        'min_funding' => env('MINIMUM_WALLET_FUNDING', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'default' => env('STORAGE_DRIVER', 'local'),
        'disks' => [
            'local' => [
                'root' => base_path('public/uploads'),
                'url'  => env('APP_URL') . '/uploads',
            ],
            's3' => [
                'key'    => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION'),
                'bucket' => env('AWS_BUCKET'),
                'url'    => env('AWS_URL'),
                'use_acl' => env('AWS_USE_ACL', false),
                'acl'     => env('AWS_ACL', 'public-read'),
            ],
        ],
    ],
];
