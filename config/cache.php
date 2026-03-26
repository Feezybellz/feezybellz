<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    | Supported: "file", "redis"
    */
    'default' => $_ENV['CACHE_DRIVER'] ?? 'file',

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    */
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../storage/framework/cache',
        ],
        'redis' => [
            'driver' => 'redis',
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['REDIS_PORT'] ?? 6379,
            'password' => $_ENV['REDIS_PASSWORD'] ?? null,
        ],
    ],
];