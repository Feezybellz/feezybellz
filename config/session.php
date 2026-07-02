<?php

/**
 * Session configuration.
 *
 * Every knob the framework's PHPSessionDriver honors lives here. The driver
 * consults this config on start(); tests and long-running workers can swap
 * values before the first request without editing framework source.
 */

return [

    /*
    | Session cookie NAME. PHP default is PHPSESSID which is a fingerprintable
    | signal that "this is a PHP app". Change it to blend in.
    */
    'cookie_name' => env('SESSION_COOKIE_NAME', ''),

    /*
    | Cookie LIFETIME in seconds. 0 means "session cookie" — dropped when
    | the browser closes. Any positive number persists that long.
    */
    'cookie_lifetime' => (int) env('SESSION_COOKIE_LIFETIME', 0),

    /*
    | Cookie PATH scope. `/` = everywhere on this host.
    */
    'cookie_path' => env('SESSION_COOKIE_PATH', '/'),

    /*
    | Cookie DOMAIN scope. Empty = current host only.
    | For subdomain-shared sessions: '.example.com'.
    */
    'cookie_domain' => env('SESSION_COOKIE_DOMAIN', ''),

    /*
    | Cookie SECURE flag. Null = auto-detect (true when HTTPS). Set to
    | true to force secure-only regardless (recommended in production).
    */
    'cookie_secure' => filter_var(env('SESSION_COOKIE_SECURE', ''), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),

    /*
    | HTTPONLY — prevents JS access via document.cookie. Always leave on
    | unless you have a very specific need.
    */
    'cookie_httponly' => filter_var(env('SESSION_COOKIE_HTTPONLY', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | SAMESITE — 'Lax' | 'Strict' | 'None'.
    |   Lax    — default, blocks most cross-site posts, allows navigation
    |   Strict — hardest, blocks even top-level navigations from other sites
    |   None   — required for third-party embeds; must be paired with Secure=true
    */
    'cookie_samesite' => env('SESSION_COOKIE_SAMESITE', 'Lax'),

    /*
    | Server-side session storage subdirectory (for the file driver).
    | Leave empty to use PHP's default session.save_path.
    */
    'save_path' => env('SESSION_SAVE_PATH', ''),

    /*
    | Use PHP's strict-session-id mode. Refuses IDs that PHP hasn't seen
    | before, defeating "attacker plants a known session ID in your
    | browser then waits for you to log in" fixation attacks.
    | Leave on.
    */
    'use_strict_mode' => filter_var(env('SESSION_STRICT_MODE', true), FILTER_VALIDATE_BOOLEAN),

];
