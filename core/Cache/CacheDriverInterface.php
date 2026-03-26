<?php

namespace Framework\Core\Cache;

use Closure;

interface CacheDriverInterface
{
    /**
     * Retrieve an item from the cache by key.
     */
    public function get(string $key, $default = null);

    /**
     * Store an item in the cache for a given number of seconds.
     */
    public function put(string $key, $value, int $ttl = 3600): bool;
    

    /**
     * Determine if an item exists in the cache.
     */
    public function has(string $key): bool;

    /**
     * Remove an item from the cache.
     */
    public function forget(string $key): bool;

    /**
     * Remove all items from the cache.
     */
    public function flush(): bool;

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     */
    public function remember(string $key, int $ttl, Closure $callback);
}