<?php

/**
 * Database Configuration
 */

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    
    'connections' => [
        'default' => [
            'driver'   => env('DB_CONNECTION', 'mysql'),
            
            'host'     => env('DB_HOST', '127.0.0.1'),
            'port'     => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'framework'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset'  => env('DB_CHARSET', 'utf8mb4'),
        ],
        'mongodb' => [
            'driver'   => 'mongodb',
            'uri'      => env('MONGO_URI'),
            'host'     => env('MONGO_HOST', '127.0.0.1'),
            'port'     => env('MONGO_PORT', 27017),
            'database' => env('MONGO_DATABASE', 'framework'),
            'username' => env('MONGO_USERNAME', ''),
            'password' => env('MONGO_PASSWORD', ''),
            'options'  => [
                'authSource' => env('MONGO_AUTH_SOURCE', 'admin'),
            ],
        ],
    ],
    // Static Tenants: Hardcoded connections (Overrides DB lookup)
    'tenants' => [
        'admin' => [
            'driver'   => 'mysql',
            'host'     => '127.0.0.1',
            'database' => 'pos_internal_admin',
            'username' => 'root',
            'password' => '',
        ]
    ]
];
