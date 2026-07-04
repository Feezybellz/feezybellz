<?php

namespace Tests;

/**
 * Test helper: inject a deterministic APP_KEY into the environment and
 * reload the `app` config so key-dependent subsystems (SignedToken,
 * ClosureSerializer, Encryption defaults) have something to sign with.
 *
 * The real .env ships APP_KEY empty on purpose — a production deploy is
 * expected to generate one — so tests must provide their own.
 */
trait WithAppKey
{
    protected function bootWithAppKey(): void
    {
        $key = 'base64:' . base64_encode(str_repeat('a', 32));
        $_ENV['APP_KEY'] = $key;
        $_SERVER['APP_KEY'] = $key;
        putenv('APP_KEY=' . $key);

        // Boot the framework, then re-read config/app.php so config('app.key')
        // reflects the key we just set (config lazy-loads and caches).
        parent::setUp();
        if (function_exists('config')) {
            config('__reload__', 'app');
        }
    }
}
