<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Proof of Work (PoW) Difficulty
    |--------------------------------------------------------------------------
    |
    | The number of leading zeros required in the SHA-256 hash collision.
    | A value of 3 takes ~50-150ms on mobile devices (seamless for humans),
    | while significantly penalizing automated bot scraping algorithms.
    |
    */
    'difficulty' => 3,

    /*
    |--------------------------------------------------------------------------
    | Challenge Expiration (Seconds)
    |--------------------------------------------------------------------------
    |
    | Time-To-Live for issued challenges. Once expired, the JWT token will be
    | rejected and the user will need to refresh the form. Also controls
    | the duration used nonces are burned into the framework Cache.
    |
    */
    'ttl' => 600, // 10 minutes

    /*
    |--------------------------------------------------------------------------
    | Minimum Time-to-Submit (Seconds)
    |--------------------------------------------------------------------------
    |
    | Real human users take at least a couple of seconds to view and complete
    | a form. Submissions occurring faster than this latency threshold from
    | the challenge generation timestamp are rejected as bot spam.
    |
    */
    'min_submit_time' => 2,

    /*
    |--------------------------------------------------------------------------
    | Passive Behavioral Verification
    |--------------------------------------------------------------------------
    |
    | When set to true, the frontend JavaScript verifies that at least one
    | human interactive DOM event occurred (pointermove, keydown, focus,
    | touchstart) between form load and submission.
    |
    */
    'verify_behavior' => true,

    /*
    |--------------------------------------------------------------------------
    | Cache Prefix for Nonce Burn
    |--------------------------------------------------------------------------
    |
    | The namespace key prefix used in Framework Cache to store consumed nonces
    | to guarantee robust replay attack protection.
    |
    */
    'cache_prefix' => 'captcha:nonce:',
];
