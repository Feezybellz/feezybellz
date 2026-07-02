<?php

/**
 * Password hashing configuration.
 *
 * `driver` is a PHP password_hash algorithm constant name (a string that
 * matches one of PASSWORD_ARGON2ID / PASSWORD_ARGON2I / PASSWORD_BCRYPT)
 * or the string value (`argon2id`, `argon2i`, `bcrypt`). Leaving it null
 * uses Argon2id when the runtime supports it, bcrypt otherwise.
 */

return [
    'driver' => env('HASH_DRIVER', null),

    'options' => [
        // Argon2 parameters (memory in KiB, iterations, parallelism).
        'memory_cost' => (int) env('HASH_MEMORY_COST', 65536),
        'time_cost'   => (int) env('HASH_TIME_COST', 4),
        'threads'     => (int) env('HASH_THREADS', 2),

        // Bcrypt cost (rounds = 2^cost).
        'cost' => (int) env('HASH_BCRYPT_COST', 12),
    ],
];
