<?php

/**
 * CORS configuration.
 *
 * Apply CORS to a route or group via the `cors` middleware alias. The middleware
 * is purely opt-in — the framework does not register it globally, so a route
 * without `cors` will send no CORS headers at all.
 *
 *   // Single route, default profile
 *   Router::get('/api/me', 'cors', [MeController::class, 'show']);
 *
 *   // Group with a named profile
 *   Router::middleware('cors:public', function () {
 *       Router::get('/api/posts', [PostController::class, 'index']);
 *   });
 *
 *   // Combine with other middleware
 *   Router::middleware(['cors:api', 'auth'], function () { ... });
 *
 * Tune profiles below. The default is intentionally restrictive: empty
 * allowed_origins means "no cross-origin request will be authorized." Add
 * concrete origins (or '*' for public APIs) per profile.
 *
 * Security rules baked into the middleware:
 *  - If allow_credentials is true, allowed_origins may NOT contain '*'.
 *    Browsers reject `Access-Control-Allow-Origin: *` with credentialed
 *    requests, and reflecting an arbitrary origin while sending credentials
 *    is a CSRF amplifier. The middleware will refuse to start with that
 *    combination.
 *  - The middleware echoes the *specific* matching origin in
 *    `Access-Control-Allow-Origin`, never a wildcard when credentials are on.
 *  - Origins are matched as exact strings, OR as a single '*' wildcard.
 *    If you need pattern matching (subdomain wildcards), keep the list
 *    small and explicit — pattern matching invites mistakes.
 */

return [

    'default_profile' => 'default',

    'profiles' => [

        /*
         * Restrictive default. A route that applies `cors` without naming a
         * profile gets this — and by default authorizes nothing. The developer
         * has to either name a different profile or fill in allowed_origins.
         */
        'default' => [
            'allowed_origins'   => [],
            'allowed_methods'   => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers'   => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-TOKEN'],
            'exposed_headers'   => [],
            'max_age'           => 86400,
            'allow_credentials' => false,
        ],

        /*
         * Open API. Suitable only for endpoints that are genuinely public,
         * idempotent, and not session-authenticated. The wildcard origin is
         * the explicit signal: there's nothing here worth protecting.
         */
        'public' => [
            'allowed_origins'   => ['*'],
            'allowed_methods'   => ['GET', 'OPTIONS'],
            'allowed_headers'   => ['Content-Type', 'Accept'],
            'exposed_headers'   => [],
            'max_age'           => 86400,
            'allow_credentials' => false,
        ],

        /*
         * Same-origin SPA hitting JSON endpoints with a session cookie. Edit
         * the origin list to match the actual frontend hosts.
         */
        'spa' => [
            'allowed_origins'   => [
                // 'https://app.example.com',
                // 'https://staging.app.example.com',
            ],
            'allowed_methods'   => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers'   => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-TOKEN'],
            'exposed_headers'   => [],
            'max_age'           => 86400,
            'allow_credentials' => true,
        ],

    ],

];
