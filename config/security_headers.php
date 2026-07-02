<?php

/**
 * Security headers configuration.
 *
 * Apply via the `security` middleware alias (opt-in):
 *
 *   Router::middleware('security', function () { ... });             // default profile
 *   Router::middleware('security:strict', function () { ... });      // hardened profile
 *   Router::middleware('security:api',    function () { ... });      // JSON API profile
 *   Router::get('/health', 'security:none', $handler);                // explicit no-op
 *
 * Every profile is just a flat map of header-name => header-value. The
 * middleware emits each entry as a literal HTTP response header — there is no
 * computed behavior. To add a custom or vendor header (CSP report endpoints,
 * permission policies, Reporting-Endpoints, anything else), just put it in
 * the profile as raw text. The middleware will pass it through unchanged.
 *
 * To suppress a header set by a parent context, give it a value of `null` —
 * the middleware skips null values. Useful when a profile inherits a base set
 * and wants to remove one entry.
 *
 * Profile precedence: if the route applies `security:<name>` the named profile
 * is used; otherwise the `default_profile` config key is used.
 */

return [

    'default_profile' => 'default',

    'profiles' => [

        /*
         * Sensible defaults for an HTML-serving app. Restrictive enough to
         * stop the easy XSS/clickjacking footguns without breaking common
         * UX features.
         */
        'default' => [
            'X-Frame-Options'        => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Permissions-Policy'     => 'camera=(self), microphone=(), geolocation=(self)',
            // 'Content-Security-Policy' => "default-src 'self'",  // uncomment + tune
            // 'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains', // HTTPS only
        ],

        /*
         * Hardened profile. Suitable for an admin area or anything handling
         * sensitive data. Locks down browser features and assumes HTTPS.
         */
        'strict' => [
            'X-Frame-Options'           => 'DENY',
            'X-Content-Type-Options'    => 'nosniff',
            'Referrer-Policy'           => 'no-referrer',
            'Permissions-Policy'        => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Content-Security-Policy'   => "default-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'",
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
            'Cross-Origin-Opener-Policy'   => 'same-origin',
            'Cross-Origin-Embedder-Policy' => 'require-corp',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ],

        /*
         * JSON API profile. Skips the HTML-specific headers (frame options,
         * permissions policy) and focuses on transport + sniff protection.
         */
        'api' => [
            'X-Content-Type-Options'    => 'nosniff',
            'Cache-Control'             => 'no-store',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Referrer-Policy'           => 'no-referrer',
        ],

        /*
         * Explicit no-op. Useful inside a parent group that applies `security`
         * when you have one route that needs to deliberately escape the
         * inherited headers (e.g. an embeddable widget endpoint).
         */
        'none' => [],

    ],

];
