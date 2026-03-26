<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Secret Key
    |--------------------------------------------------------------------------
    |
    | This key is used to sign JWT tokens. Keep this secret and secure.
    | Generate a strong random string for production use.
    |
    */
    'secret' => $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-this-in-production',

    /*
    |--------------------------------------------------------------------------
    | JWT Token Expiration (in seconds)
    |--------------------------------------------------------------------------
    |
    | Default token expiration time. Can be overridden when creating tokens.
    | Default: 3600 seconds (1 hour)
    |
    */
    'expiration' => (int) ($_ENV['JWT_EXPIRATION'] ?? 3600),

    /*
    |--------------------------------------------------------------------------
    | JWT Refresh Token Expiration (in seconds)
    |--------------------------------------------------------------------------
    |
    | Refresh token expiration time.
    | Default: 604800 seconds (7 days)
    |
    */
    'refresh_expiration' => (int) ($_ENV['JWT_REFRESH_EXPIRATION'] ?? 604800),

    /*
    |--------------------------------------------------------------------------
    | JWT Algorithm
    |--------------------------------------------------------------------------
    |
    | The algorithm used to sign tokens.
    | Supported: HS256, HS384, HS512
    |
    */
    'algorithm' => $_ENV['JWT_ALGORITHM'] ?? 'HS256',

    /*
    |--------------------------------------------------------------------------
    | JWT Issuer
    |--------------------------------------------------------------------------
    |
    | The issuer of the token (optional)
    |
    */
    'issuer' => $_ENV['JWT_ISSUER'] ?? 'pos-system',

    /*
    |--------------------------------------------------------------------------
    | JWT Audience
    |--------------------------------------------------------------------------
    |
    | The audience of the token (optional)
    |
    */
    'audience' => $_ENV['JWT_AUDIENCE'] ?? 'pos-api',
];
