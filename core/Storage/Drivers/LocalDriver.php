<?php

namespace Framework\Core\Storage\Drivers;

class LocalDriver implements StorageDriverInterface
{
    protected $root;
    protected $url;

    public function __construct(array $config)
    {
        $this->root = rtrim($config['root'], '/');
        $this->url = rtrim($config['url'] ?? '/storage', '/');
        
        if (!is_dir($this->root)) {
            mkdir($this->root, 0755, true);
        }
    }

    protected function getAbsolutePath(string $path): string
    {
        return $this->root . '/' . ltrim($path, '/');
    }

    public function put(string $path, $contents): bool
    {
        $absPath = $this->getAbsolutePath($path);
        $dir = dirname($absPath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($absPath, $contents) !== false;
    }

    public function get(string $path): ?string
    {
        $absPath = $this->getAbsolutePath($path);
        if (!file_exists($absPath)) return null;
        
        return file_get_contents($absPath) ?: null;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->getAbsolutePath($path));
    }

    public function delete(string $path): bool
    {
        $absPath = $this->getAbsolutePath($path);
        if (file_exists($absPath)) {
            return unlink($absPath);
        }
        return false;
    }

    public function url(string $path): string
    {
        return $this->url . '/' . ltrim($path, '/');
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
    {
        throw new \Exception("This driver does not support generating temporary URLs.");
    }

    public function temporaryUploadUrl(string $path, \DateTimeInterface $expiration, array $options = []): array
    {
        throw new \Exception("This driver does not support generating temporary upload URLs.");
    }
}
