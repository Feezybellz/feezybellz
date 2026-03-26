<?php

return [
    'client_id'     => env('GOOGLE_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
    'redirect_uri'  => env('GOOGLE_REDIRECT_URI', env('APP_URL', 'http://localhost') . '/auth/google/callback'),
];
