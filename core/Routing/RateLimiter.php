<?php

namespace Framework\Core\Routing;

use Framework\Core\Cache\Cache;

class RateLimiter
{
    /**
     * Determine if the given key has been "accessed" too many times.
     */
    public static function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        if (self::attempts($key) >= $maxAttempts) {
            if (Cache::has($key . ':timer')) {
                return true;
            }
            self::resetAttempts($key);
        }

        return false;
    }

    /**
     * Increment the counter for a given key for a given decay time.
     *
     * We put() the counter with the caller-supplied decay on the first hit
     * so its TTL matches the timer's, then increment() from there. If we
     * relied on increment()'s implicit-create path, the counter would use
     * whatever default TTL the driver picked (an hour in FileDriver) which
     * silently outlives the timer and breaks tooManyAttempts().
     */
    public static function hit(string $key, int $decaySeconds = 60): int
    {
        $timerKey = $key . ':timer';

        // If the counter doesn't exist yet, seed it with the correct TTL so
        // its lifetime matches the caller's decay window.
        if (!Cache::has($key)) {
            Cache::put($key, 0, $decaySeconds);
            Cache::put($timerKey, time() + $decaySeconds, $decaySeconds);
        }

        return Cache::increment($key);
    }

    /**
     * Get the number of attempts for the given key.
     */
    public static function attempts(string $key): int
    {
        return (int) Cache::get($key, 0);
    }

    /**
     * Reset the number of attempts for the given key.
     */
    public static function resetAttempts(string $key): bool
    {
        Cache::forget($key . ':timer');
        return Cache::forget($key);
    }

    /**
     * Get the number of seconds until the key is available again.
     */
    public static function availableIn(string $key): int
    {
        $timer = Cache::get($key . ':timer');
        return $timer ? max(0, $timer - time()) : 0;
    }

    /**
     * Clear the hits and timer for the given key.
     */
    public static function clear(string $key): void
    {
        self::resetAttempts($key);
    }
}
