<?php

/**
 * CSRF configuration.
 *
 * Apply CSRF to a route or group via the `csrf` middleware alias. The
 * middleware is purely opt-in — the framework does not register it globally.
 * Apply it explicitly to anything stateful and reachable from a browser:
 *
 *   Router::middleware('csrf', function () {
 *       Router::post('/users',  [UserController::class, 'store']);
 *       Router::patch('/users/{id}', [UserController::class, 'update']);
 *   });
 *
 *   // Per-route, alongside other middleware
 *   Router::post('/orders', 'auth', 'csrf', [OrderController::class, 'store']);
 *
 * The middleware accepts a profile name as the parameter:
 *
 *   Router::middleware('csrf:default',  function () { ... });   // standard rules
 *   Router::middleware('csrf:strict',   function () { ... });   // ignore the `except` list
 *   Router::middleware('csrf:skip',     function () { ... });   // bypass — useful inside a
 *                                                               //   group that otherwise has csrf
 *
 * Webhooks and other external callers that can't supply a token should either
 * live in a route group without the alias at all, or be listed in
 * `profiles.default.except`. Keep the except list short.
 *
 * The token itself is read from:
 *   1. The request body field configured under `input_name`.
 *   2. Any of the headers configured under `header_names`.
 * Whichever arrives first wins. Comparison is constant-time.
 */

return [

    'default_profile' => 'default',

    'profiles' => [

        'default' => [
            // HTTP methods that require a valid token. Safe verbs are always exempt.
            'methods' => ['POST', 'PUT', 'PATCH', 'DELETE'],

            // Where the middleware looks for the submitted token.
            'input_name'   => '_csrf_token',
            'header_names' => ['X-CSRF-TOKEN', 'X-XSRF-TOKEN'],

            // URIs (or glob patterns) excluded from CSRF verification while this
            // profile is in effect. Use sparingly — each entry is an
            // attack-surface concession.
            'except' => [
                // '/api/webhooks/stripe',
                // '/api/webhooks/*',
            ],
        ],

        // Same rules as default but with no exceptions — apply to a sensitive
        // group where you want webhooks etc. to be impossible to bypass.
        'strict' => [
            'methods'      => ['POST', 'PUT', 'PATCH', 'DELETE'],
            'input_name'   => '_csrf_token',
            'header_names' => ['X-CSRF-TOKEN', 'X-XSRF-TOKEN'],
            'except'       => [],
        ],

        // No-op profile so a group middleware of `csrf` can be locally disabled
        // for a sub-route without restructuring the route tree.
        'skip' => [
            'methods'      => [],
            'input_name'   => '_csrf_token',
            'header_names' => ['X-CSRF-TOKEN', 'X-XSRF-TOKEN'],
            'except'       => [],
        ],

    ],

];
