<?php

namespace Framework\Core\Cache\Drivers;

use Framework\Core\Cache\CacheDriverInterface;
use Closure;

class FileDriver implements CacheDriverInterface
{
    protected $cachePath;

    public function __construct(array $config)
    {
        $this->cachePath = $config['path'] ?? __DIR__ . '/../../../../storage/framework/cache';
        
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0777, true);
        }
    }

    protected function getFilePath(string $key): string
    {
        // Hash the key to create a safe, filesystem-friendly filename
        return $this->cachePath . '/' . md5($key) . '.cache';
    }

    public function get(string $key, $default = null)
    {
        $path = $this->getFilePath($key);

        if (!file_exists($path)) {
            return $default;
        }

        $contents = file_get_contents($path);
        $cache = unserialize($contents);

        // Check if the cache has expired
        if (time() >= $cache['expires_at']) {
            $this->forget($key);
            return $default;
        }

        return $cache['data'];
    }

    public function put(string $key, $value, int $ttl = 3600): bool
    {
        $path = $this->getFilePath($key);
        
        $cache = [
            'expires_at' => time() + $ttl,
            'data' => $value
        ];

        return file_put_contents($path, serialize($cache), LOCK_EX) !== false;
    }

    public function increment(string $key, int $value = 1): int
    {
        $path = $this->getFilePath($key);

        $fp = fopen($path, 'c+');
        if (!$fp) return 0;

        flock($fp, LOCK_EX);

        $contents = stream_get_contents($fp);
        if ($contents) {
            $cache = unserialize($contents);
            if (time() >= $cache['expires_at']) {
                $cache = ['expires_at' => time() + 3600, 'data' => 0];
            }
        } else {
            $cache = ['expires_at' => time() + 3600, 'data' => 0];
        }

        $cache['data'] += $value;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, serialize($cache));
        flock($fp, LOCK_UN);
        fclose($fp);

        return $cache['data'];
    }

    public function decrement(string $key, int $value = 1): int
    {
        return $this->increment($key, $value * -1);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function forget(string $key): bool
    {
        $path = $this->getFilePath($key);
        if (file_exists($path)) {
            return unlink($path);
        }
        return false;
    }

    public function flush(): bool
    {
        $files = glob($this->cachePath . '/*.cache');
        $success = true;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $success = $success && unlink($file);
            }
        }
        
        return $success;
    }

    public function remember(string $key, int $ttl, Closure $callback)
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        // Execute the database query or expensive logic
        $value = $callback();
        
        $this->put($key, $value, $ttl);

        return $value;
    }
}
