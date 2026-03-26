<?php

return [
    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY', ''),
        'public_key' => env('PAYSTACK_PUBLIC_KEY', ''),
        'webhook_url' => env('APP_URL') . '/api/v1/payments/webhook',
    ],
    'currency' => 'NGN',
];
