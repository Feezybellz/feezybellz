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

    public function move(string $from, string $to): bool
    {
        $absFrom = $this->getAbsolutePath($from);
        $absTo = $this->getAbsolutePath($to);
        
        if (!file_exists($absFrom)) {
            return false;
        }
        
        $dir = dirname($absTo);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return rename($absFrom, $absTo);
    }

    public function copy(string $from, string $to): bool
    {
        $absFrom = $this->getAbsolutePath($from);
        $absTo = $this->getAbsolutePath($to);
        
        if (!file_exists($absFrom)) return false;
        
        $dir = dirname($absTo);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
        return copy($absFrom, $absTo);
    }

    public function size(string $path): int
    {
        return filesize($this->getAbsolutePath($path)) ?: 0;
    }

    public function lastModified(string $path): int
    {
        return filemtime($this->getAbsolutePath($path)) ?: 0;
    }

    public function mimeType(string $path)
    {
        $absPath = $this->getAbsolutePath($path);
        if (!file_exists($absPath)) return false;
        return mime_content_type($absPath);
    }

    public function files(string $directory): array
    {
        $absDir = $this->getAbsolutePath($directory);
        if (!is_dir($absDir)) return [];
        
        $files = [];
        $iterator = new \FilesystemIterator($absDir);
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = ltrim(str_replace($this->root, '', $file->getPathname()), '/');
            }
        }
        return $files;
    }

    public function directories(string $directory): array
    {
        $absDir = $this->getAbsolutePath($directory);
        if (!is_dir($absDir)) return [];
        
        $directories = [];
        $iterator = new \FilesystemIterator($absDir);
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                $directories[] = ltrim(str_replace($this->root, '', $file->getPathname()), '/');
            }
        }
        return $directories;
    }

    public function deleteDirectory(string $directory): bool
    {
        $absDir = $this->getAbsolutePath($directory);
        if (!is_dir($absDir)) return false;
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        return rmdir($absDir);
    }

    public function readStream(string $path)
    {
        $absPath = $this->getAbsolutePath($path);
        if (!file_exists($absPath)) return null;
        return fopen($absPath, 'rb');
    }

    public function writeStream(string $path, $resource): bool
    {
        $absPath = $this->getAbsolutePath($path);
        $dir = dirname($absPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
        $dest = fopen($absPath, 'wb');
        if (!$dest) return false;
        
        $success = stream_copy_to_stream($resource, $dest);
        fclose($dest);
        
        return $success !== false;
    }
}
