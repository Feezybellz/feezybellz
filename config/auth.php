<?php

/**
 * Auth configuration.
 *
 * The framework's Auth layer is deliberately opinion-free about "user."
 * It never reads a database, never assumes a table, never assumes a
 * credential shape. Each named guard here just says "how does this
 * request tell me who's here?" — the developer decides everything else.
 *
 * Available drivers:
 *   session   — cookie-backed, rotates on login (fixation defence)
 *   jwt       — stateless bearer token; login() returns the token string
 *   callable  — developer supplies a resolver closure and (optional)
 *               login/logout handlers. For basic auth, API keys, HMAC-
 *               signed requests, or anything custom.
 *
 * Add your own driver by calling
 *   Auth::manager()->extend('name', fn() => new MyGuard(...));
 * anywhere before the guard is first used.
 */

return [

    /*
    | Which guard responds to bare Auth::check() / Auth::user() calls.
    | Named-guard access is always available via Auth::guard('name').
    */
    'default' => env('AUTH_DEFAULT_GUARD', 'web'),

    /*
    | Route middleware failure redirect target (HTML requests only).
    | JSON requests get a 401.
    */
    'login_url' => env('AUTH_LOGIN_URL', '/login'),

    /*
    | Guard definitions.
    */
    'guards' => [

        // Browser-facing session guard.
        'web' => [
            'driver'      => 'session',
            'session_key' => '_auth_web',
        ],

        // API JWT bearer guard. `ttl` is the token lifetime in seconds.
        // The full JWT wire configuration (issuer, audience, algorithm,
        // signing key rotation, leeway) lives in config/jwt.php.
        'api' => [
            'driver' => 'jwt',
            'ttl'    => (int) env('AUTH_JWT_TTL', 3600),
        ],

        // Example: an API-key guard using the callable driver.
        // Uncomment and adapt.
        //
        // 'apikey' => [
        //     'driver'   => 'callable',
        //     'resolver' => function (\Framework\Core\Http\Request $r) {
        //         $key = $r->header('X-API-Key');
        //         return $key ? \App\Services\ApiKeys::find($key) : null;
        //     },
        //     'login'  => null,
        //     'logout' => null,
        // ],

    ],

];
