<?php

namespace Framework\Core\Cache\Drivers;

use Framework\Core\Cache\CacheDriverInterface;
use Closure;
use Exception;
use Memcached;

class MemcachedDriver implements CacheDriverInterface
{
    protected $memcached;

    public function __construct(array $config)
    {
        if (!class_exists('Memcached')) {
            $msg = \Framework\Core\Support\SystemSetup::getExtensionInstallMessage('memcached', 'memcached', 11211);
            throw new Exception($msg);
        }

        $persistentId = $config['persistent_id'] ?? null;

        $this->memcached = new Memcached($persistentId);

        // Only add servers if we just instantiated a new persistent connection
        // or if we are not using persistent connections at all.
        if (empty($this->memcached->getServerList())) {
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 11211;
            $weight = $config['weight'] ?? 100;
            
            $this->memcached->addServer($host, $port, $weight);
        }

        if (!empty($config['prefix'])) {
            $this->memcached->setOption(Memcached::OPT_PREFIX_KEY, $config['prefix']);
        }
    }

    public function get(string $key, $default = null)
    {
        $value = $this->memcached->get($key);

        if ($this->memcached->getResultCode() === Memcached::RES_NOTFOUND) {
            return is_callable($default) ? $default() : $default;
        }

        return $value;
    }

    public function put(string $key, $value, int $ttl = 3600): bool
    {
        return $this->memcached->set($key, $value, $ttl);
    }

    public function increment(string $key, int $value = 1): int
    {
        // Memcached doesn't increment keys that don't exist yet natively via increment().
        // If it doesn't exist, we must add it first.
        $result = $this->memcached->increment($key, $value);
        if ($result === false) {
            $this->memcached->set($key, $value);
            return $value;
        }
        return $result;
    }

    public function decrement(string $key, int $value = 1): int
    {
        $result = $this->memcached->decrement($key, $value);
        if ($result === false) {
            $this->memcached->set($key, 0);
            return 0;
        }
        return $result;
    }

    public function has(string $key): bool
    {
        $this->memcached->get($key);
        return $this->memcached->getResultCode() !== Memcached::RES_NOTFOUND;
    }

    public function forget(string $key): bool
    {
        return $this->memcached->delete($key);
    }

    public function flush(): bool
    {
        return $this->memcached->flush();
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
