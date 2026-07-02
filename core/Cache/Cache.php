<?php

namespace Framework\Core\Cache;

use Framework\Core\Cache\Drivers\FileDriver;

class Cache
{

    /**
     * @var CacheDriverInterface|null The active driver instance.
     */
    private static $driver = null;

    /**
     * Swap the active driver. Primary use cases:
     *   - Tests: inject an in-memory fake to keep suites hermetic.
     *   - Long-running workers: hot-swap between per-tenant caches without
     *     tearing down the whole process.
     *
     * Example:
     *   Cache::setDriver(new \Framework\Core\Cache\Drivers\FileDriver(['path' => sys_get_temp_dir()]));
     */
    public static function setDriver(CacheDriverInterface $driver): void
    {
        self::$driver = $driver;
    }

    /**
     * Clear the cached driver. The next Cache call rebuilds from config.
     * Use this between test cases, or when config('cache') has changed at runtime.
     */
    public static function reset(): void
    {
        self::$driver = null;
    }

    /**
     * Return the active driver, building it lazily from config if needed.
     * Rarely called directly — exposed for tests and advanced usage.
     */
    public static function driver(): CacheDriverInterface
    {
        self::init();
        return self::$driver;
    }

    private static function init(): void
    {
        if (self::$driver !== null) {
            return;
        }

        $config = function_exists('config') ? config('cache') : self::getDefaultConfig();
        $defaultDriver = $config['default'] ?? 'file';

        switch ($defaultDriver) {
            case 'file':
                self::$driver = new FileDriver($config['stores']['file'] ?? []);
                break;
            case 'redis':
                self::$driver = new Drivers\RedisDriver($config['stores']['redis'] ?? []);
                break;
            case 'memcached':
                self::$driver = new Drivers\MemcachedDriver($config['stores']['memcached'] ?? []);
                break;
            default:
                throw new \Exception("Cache driver [{$defaultDriver}] is not supported.");
        }
    }

    private static function getDefaultConfig(): array
    {
        return [
            'default' => 'file',
            'stores' => [
                'file' => ['path' => __DIR__ . '/../../../storage/framework/cache']
            ]
        ];
    }

    // --- Static Forwarding Magic ---

    public static function get(string $key, $default = null)
    {
        self::init();
        return self::$driver->get($key, $default);
    }

    public static function set(string $key, $value, int $ttl = 3600): bool
    {
        self::init();
        return self::$driver->put($key, $value, $ttl);
    }
    public static function put(string $key, $value, int $ttl = 3600): bool
    {
        self::init();
        return self::$driver->put($key, $value, $ttl);
    }

    public static function increment(string $key, int $value = 1): int
    {
        self::init();
        return self::$driver->increment($key, $value);
    }

    public static function decrement(string $key, int $value = 1): int
    {
        self::init();
        return self::$driver->decrement($key, $value);
    }

    public static function has(string $key): bool
    {
        self::init();
        return self::$driver->has($key);
    }

    public static function forget(string $key): bool
    {
        self::init();
        return self::$driver->forget($key);
    }

    public static function flush(): bool
    {
        self::init();
        return self::$driver->flush();
    }

    public static function remember(string $key, int $ttl, \Closure $callback)
    {
        self::init();
        return self::$driver->remember($key, $ttl, $callback);
    }
}

/* Usage Example */
/*
Cache::put('user', $user, 3600); // Store user for 1 hour
$user = Cache::get('user'); // Retrieve user

clear 
Cache::forget('user'); // Remove user from cache
Cache::flush(); // Remove all cache


*/
