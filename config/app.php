<?php

/**
 * Application Configuration
 */

return [
    'name'     => env('APP_NAME', 'Framework Application'),
    'env'      => env('APP_ENV', 'production'),
    'debug'    => env('APP_DEBUG', false),
    'url'      => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    | Encryption key. Encryption::resolveKey() reads config('app.key') first,
    | falling back to env('APP_KEY') if this is blank. Generate one with
    | `php console make:env --generate-key --force` (writes a base64:... key
    | to .env; this config line then picks it up via env()).
    */
    'key' => env('APP_KEY', ''),

    /*
    | Comma-separated list of domains this app serves. The Router uses this
    | to distinguish "the apex host" from subdomains and to prevent
    | subdomain-bleeding when APP_DOMAIN is set. Empty = permissive matching.
    | Example: APP_DOMAIN=localhost,example.com,staging.example.com
    */
    'domain' => env('APP_DOMAIN', ''),

    /*
    | When true, the exception Handler always returns JSON regardless of the
    | Accept header. Handy for APIs that don't want the HTML error page ever.
    */
    'force_json' => env('APP_FORCE_JSON', false),

    /*
    | Legacy top-level shortcut for the JWT secret. Prefer config('jwt.secret')
    | going forward — it supports key-rotation arrays too.
    */
    'jwt_secret' => env('JWT_SECRET', ''),

    'google_map_api_key' => env('GOOGLE_MAP_API_KEY', ''),
    'ws_url'             => env('WS_URL', ''),

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
                // Storage OUTSIDE the web root by default. Serving raw
                // uploads is a common RCE pivot (SVG XSS, executable HTML,
                // .htaccess uploads, etc.). Route access through a
                // controller that serves via readfile() with the right
                // Content-Type and Content-Disposition instead.
                //
                // To restore the old public-web behavior, set STORAGE_ROOT
                // to base_path('public/uploads') in .env.
                'root' => env('STORAGE_ROOT', base_path('storage/uploads')),
                'url'  => (env('APP_URL', 'http://localhost')) . env('STORAGE_URL_PATH', '/uploads'),
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
