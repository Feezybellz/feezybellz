<?php

return [
    'default' => $_ENV['PUSH_DRIVER'] ?? 'web', // Set default to 'web'

    'fcm' => [
        'server_key' => $_ENV['FCM_SERVER_KEY'] ?? '',
    ],

    // Add this block for Web Push
    'web' => [
        'subject'     => $_ENV['VAPID_SUBJECT'] ?? 'mailto:admin@localhost.com',
        'public_key'  => $_ENV['VAPID_PUBLIC_KEY'] ?? '',
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
        'legacy_server_key' => env('FCM_SERVER_KEY', ''),
    ],
    ];