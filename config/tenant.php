<?php

return [
    'tenants' => [
        'tenant1' => [
            'driver' => 'mysql',
            'host' => env('TENANT1_DB_HOST', '127.0.0.1'),
            'database' => env('TENANT1_DB_DATABASE', 'framework_tenant1'),
            'username' => env('TENANT1_DB_USERNAME', 'root'),
            'password' => env('TENANT1_DB_PASSWORD', ''),
            'port' => (int) env('TENANT1_DB_PORT', 3306),
        ],
        'tenant2' => [
            'driver' => 'mysql',
            'host' => env('TENANT2_DB_HOST', '127.0.0.1'),
            'database' => env('TENANT2_DB_DATABASE', 'framework_tenant2'),
            'username' => env('TENANT2_DB_USERNAME', 'root'),
            'password' => env('TENANT2_DB_PASSWORD', ''),
            'port' => (int) env('TENANT2_DB_PORT', 3306),
        ],
        'tenant3' => [
            'driver' => 'mysql',
            'host' => env('TENANT3_DB_HOST', '127.0.0.1'),
            'database' => env('TENANT3_DB_DATABASE', 'framework_tenant3'),
            'username' => env('TENANT3_DB_USERNAME', 'root'),
            'password' => env('TENANT3_DB_PASSWORD', ''),
            'port' => (int) env('TENANT3_DB_PORT', 3306),
        ],
    ]
];
