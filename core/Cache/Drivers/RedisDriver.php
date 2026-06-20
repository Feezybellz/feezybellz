<?php

namespace Framework\Core\Cache\Drivers;

use Framework\Core\Cache\CacheDriverInterface;
use Closure;
use Exception;
use Redis;

class RedisDriver implements CacheDriverInterface
{
    protected $redis;

    public function __construct(array $config)
    {
        if (!extension_loaded('redis')) {
            $msg = \Framework\Core\Support\SystemSetup::getExtensionInstallMessage('redis', 'redis', 6379);
            throw new Exception($msg);
        }

        $this->redis = new Redis();

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $timeout = $config['timeout'] ?? 0.0;

        if (!$this->redis->connect($host, $port, $timeout)) {
            throw new Exception("Could not connect to Redis server at {$host}:{$port}");
        }

        if (!empty($config['password'])) {
            $this->redis->auth($config['password']);
        }

        if (isset($config['database'])) {
            $this->redis->select((int) $config['database']);
        }

        if (!empty($config['prefix'])) {
            $this->redis->setOption(Redis::OPT_PREFIX, $config['prefix']);
        }
    }

    public function get(string $key, $default = null)
    {
        $value = $this->redis->get($key);

        if ($value === false) {
            return is_callable($default) ? $default() : $default;
        }

        return is_numeric($value) ? $value : unserialize($value);
    }

    public function put(string $key, $value, int $ttl = 3600): bool
    {
        $value = is_numeric($value) ? $value : serialize($value);
        return $this->redis->setex($key, $ttl, $value);
    }

    public function increment(string $key, int $value = 1): int
    {
        return $this->redis->incrBy($key, $value);
    }

    public function decrement(string $key, int $value = 1): int
    {
        return $this->redis->decrBy($key, $value);
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($key) > 0;
    }

    public function forget(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }

    public function flush(): bool
    {
        return $this->redis->flushDB();
    }

    public function remember(string $key, int $ttl, Closure $callback)
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }
}
