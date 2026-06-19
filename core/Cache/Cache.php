<?php

namespace Framework\Core\Cache;

use Framework\Core\Cache\Drivers\FileDriver;

class Cache
{

    /*
    Upgraed to be able to use tcp connection 
    */
    private static $driver = null;

    private static function init(): void
    {
        if (self::$driver !== null) {
            return;
        }

        // Load configuration
        $config = function_exists('config') ? config('cache') : self::getDefaultConfig();
        $defaultDriver = $config['default'] ?? 'file';

        // Instantiate the correct driver
        switch ($defaultDriver) {
            case 'file':
                self::$driver = new FileDriver($config['stores']['file'] ?? []);
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
