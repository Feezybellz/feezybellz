<?php

namespace Framework\Core;

use Framework\Core\Cache\Cache;
use Framework\Core\Database\DB;
use Framework\Core\Routing\Router;
use Framework\Core\Storage\Storage;

/**
 * Per-request state reset helper.
 *
 * PHP-FPM starts a fresh process per request, so the framework's static
 * facades (DB, Cache, Storage, Router, ...) get a clean slate for free.
 * Long-running SAPIs — queue workers, WebSocket servers, Swoole/RoadRunner —
 * do NOT get that reset. State from one request will silently leak into the
 * next unless something explicitly clears it.
 *
 * This class is that "something". Call `State::resetPerRequest()` between
 * requests in any long-running context, and between test cases in unit tests
 * that touch static facades.
 *
 * What gets reset:
 *   - Router routes, group stack, sort flag, reflection cache, parsed
 *     APP_DOMAIN cache, listener middleware wiring.
 *   - DB open drivers (via disconnect() where the driver supports it) and
 *     registered query listeners. Connection *configs* are preserved so the
 *     next call still knows how to connect.
 *   - Cache driver (forces rebuild from config on next call).
 *   - Storage disks (forces rebuild from config on next call).
 *
 * What is NOT reset:
 *   - Container bindings. Those are process-scoped by design.
 *   - Environment variables and config values.
 *   - Session state (that's owned by the session driver).
 *   - Encryption keys / JWT secrets (those come from config).
 *
 * Usage in a queue worker:
 *
 *     while ($job = $queue->pop()) {
 *         try {
 *             $job->handle();
 *         } finally {
 *             \Framework\Core\State::resetPerRequest();
 *         }
 *     }
 *
 * Usage in a test suite (PHPUnit-style):
 *
 *     protected function tearDown(): void {
 *         \Framework\Core\State::resetPerRequest();
 *     }
 */
class State
{
    /**
     * Reset all per-request static state to a clean slate.
     */
    public static function resetPerRequest(): void
    {
        // Router: routes, group stack, reflection cache, APP_DOMAIN cache.
        if (class_exists(Router::class)) {
            Router::clearRoutes();
        }

        // DB: close open drivers, drop query listeners. Connection configs
        // stay so a new request can lazy-open them again.
        if (class_exists(DB::class)) {
            DB::purgeAll();
            DB::clearListeners();
        }

        // Cache: drop the resolved driver; next call re-reads config.
        if (class_exists(Cache::class)) {
            Cache::reset();
        }

        // Storage: drop resolved disks.
        if (class_exists(Storage::class)) {
            Storage::reset();
        }

        // N+1 detector: reset per-request query counters.
        if (class_exists(\Framework\Core\Database\NPlusOneDetector::class)) {
            \Framework\Core\Database\NPlusOneDetector::reset();
        }

        // Log ambient context — request_id etc. Rebuilt on next request.
        if (class_exists(\Framework\Core\Logging\Log::class)) {
            \Framework\Core\Logging\Log::clearContext();
        }
    }

    /**
     * Full teardown — for shutting a worker down cleanly, or between wildly
     * different test suites. Same as resetPerRequest() but also clears DB
     * connection *configs* so the next call has to re-register them.
     */
    public static function resetAll(): void
    {
        self::resetPerRequest();

        // Currently DB has no "forget all configs" primitive; if that's added
        // later, wire it here.
    }
}
