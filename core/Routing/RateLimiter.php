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
     */
    public static function hit(string $key, int $decaySeconds = 60): int
    {
        $timerKey = $key . ':timer';

        if (!Cache::has($timerKey)) {
            Cache::put($timerKey, time() + $decaySeconds, $decaySeconds);
        }

        $hits = Cache::increment($key);

        if ($hits === 1) {
            Cache::put($timerKey, time() + $decaySeconds, $decaySeconds);
        }

        return $hits;
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
