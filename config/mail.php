<?php

return [
    'default' => $_ENV['MAIL_MAILER'] ?? 'log', // Options: smtp, log, native

    'from' => [
        'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@pos.local',
        'name'    => $_ENV['MAIL_FROM_NAME'] ?? 'POS System',
    ],

    'smtp' => [
        'host'       => $_ENV['MAIL_HOST'] ?? '127.0.0.1',
        'port'       => $_ENV['MAIL_PORT'] ?? 2525,
        'username'   => $_ENV['MAIL_USERNAME'] ?? null,
        'password'   => $_ENV['MAIL_PASSWORD'] ?? null,
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
    ],
];