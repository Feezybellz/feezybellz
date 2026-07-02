<?php

/**
 * Push notification configuration.
 *
 * All keys go through env() so `make:env` extracts them.
 */

return [

    /*
    | Which push driver to use. Supported: 'web' (VAPID / Web Push), 'fcm'.
    */
    'default' => env('PUSH_DRIVER', 'web'),

    /*
    | Firebase Cloud Messaging (legacy HTTP API).
    */
    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY', ''),
    ],

    /*
    | Web Push (VAPID). Generate keypair with: php console push:generate
    */
    'web' => [
        'subject'           => env('VAPID_SUBJECT', 'mailto:admin@localhost.com'),
        'public_key'        => env('VAPID_PUBLIC_KEY', ''),
        'private_key'       => env('VAPID_PRIVATE_KEY', ''),
        'legacy_server_key' => env('FCM_SERVER_KEY', ''),
    ],

];
