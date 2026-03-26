<?php

/**
 * Database Configuration
 */

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    
    'connections' => [
        'default' => [
            'driver'   => env('DB_CONNECTION', 'mysql'),
            
            // Priority 1: Use a full connection URI if provided
            'uri'      => env('MONGO_URI') ?: env('DB_URI'),
            
            // Priority 2: Fallback to individual keys
            'host'     => env('MONGO_HOST') ?: env('DB_HOST') ?: 'localhost',
            'port'     => env('MONGO_PORT') ?: env('DB_PORT') ?: (env('DB_CONNECTION') === 'mongodb' ? 27017 : 3306),
            'database' => env('MONGO_DATABASE') ?: env('DB_DATABASE') ?: 'framework',
            'username' => env('MONGO_USERNAME') ?: env('DB_USERNAME') ?: '',
            'password' => env('MONGO_PASSWORD') ?: env('DB_PASSWORD') ?: '',
            'charset'  => env('DB_CHARSET', 'utf8mb4'),
            'options'  => [
                'authSource' => env('MONGO_AUTH_SOURCE', 'admin'),
            ],
        ],
    ],
    // Static Tenants: Hardcoded connections (Overrides DB lookup)
    'static_tenants' => [
        'admin' => [
            'driver'   => 'mysql',
            'host'     => '127.0.0.1',
            'database' => 'pos_internal_admin',
            'username' => 'root',
            'password' => '',
        ]
    ]
];
