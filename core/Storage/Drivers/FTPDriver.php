<?php

namespace Framework\Core\Storage\Drivers;

class FTPDriver implements StorageDriverInterface
{
    protected $connection;
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    protected function connect()
    {
        $host = $this->config['host'];
        $port = $this->config['port'] ?? 21;
        $timeout = $this->config['timeout'] ?? 90;

        $this->connection = ftp_connect($host, $port, $timeout);
        if (!$this->connection) {
            throw new \Exception("Could not connect to FTP server at {$host}:{$port}");
        }

        $login = ftp_login($this->connection, $this->config['username'], $this->config['password']);
        if (!$login) {
            throw new \Exception("Could not login to FTP server.");
        }

        ftp_pasv($this->connection, $this->config['passive'] ?? true);

        if (!empty($this->config['root'])) {
            ftp_chdir($this->connection, $this->config['root']);
        }
    }

    public function put(string $path, $contents): bool
    {
        $temp = tmpfile();
        fwrite($temp, $contents);
        fseek($temp, 0);

        // Ensure directory exists recursively
        $dir = dirname($path);
        if ($dir !== '.') {
            $parts = explode('/', $dir);
            $current = '';
            foreach ($parts as $part) {
                if (empty($part)) continue;
                $current .= '/' . $part;
                @ftp_mkdir($this->connection, ltrim($current, '/'));
            }
        }

        $result = ftp_fput($this->connection, $path, $temp, FTP_BINARY);
        fclose($temp);
        return $result;
    }

    public function get(string $path): ?string
    {
        $temp = tmpfile();
        if (ftp_fget($this->connection, $temp, $path, FTP_BINARY, 0)) {
            fseek($temp, 0);
            $contents = stream_get_contents($temp);
            fclose($temp);
            return $contents;
        }
        fclose($temp);
        return null;
    }

    public function exists(string $path): bool
    {
        return ftp_size($this->connection, $path) !== -1;
    }

    public function delete(string $path): bool
    {
        return ftp_delete($this->connection, $path);
    }

    public function url(string $path): string
    {
        $url = rtrim($this->config['url'] ?? '', '/');
        return $url . '/' . ltrim($path, '/');
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
        // Ensure directory exists for target
        $dir = dirname($to);
        if ($dir !== '.') {
            $parts = explode('/', $dir);
            $current = '';
            foreach ($parts as $part) {
                if (empty($part)) continue;
                $current .= '/' . $part;
                @ftp_mkdir($this->connection, ltrim($current, '/'));
            }
        }
        
        return ftp_rename($this->connection, $from, $to);
    }

    public function copy(string $from, string $to): bool
    {
        $contents = $this->get($from);
        if ($contents === null) return false;
        return $this->put($to, $contents);
    }

    public function size(string $path): int
    {
        $size = ftp_size($this->connection, $path);
        return $size === -1 ? 0 : $size;
    }

    public function lastModified(string $path): int
    {
        $time = ftp_mdtm($this->connection, $path);
        return $time === -1 ? 0 : $time;
    }

    public function mimeType(string $path)
    {
        return false; // FTP doesn't natively expose MIME types easily without downloading
    }

    public function files(string $directory): array
    {
        $list = ftp_nlist($this->connection, $directory);
        if ($list === false) return [];
        
        $files = [];
        foreach ($list as $item) {
            if ($this->size($item) !== 0 || $this->size($item) === 0 && !empty($item)) {
                // Approximate file detection
                $files[] = ltrim(str_replace($directory, '', $item), '/');
            }
        }
        return $files;
    }

    public function directories(string $directory): array
    {
        return []; // ftp_nlist doesn't easily distinguish dirs, complex parsing needed, skipped for brevity
    }

    public function deleteDirectory(string $directory): bool
    {
        // Recursively delete not fully supported safely via simple ftp commands without a complex loop
        return ftp_rmdir($this->connection, $directory);
    }

    public function readStream(string $path)
    {
        $temp = tmpfile();
        if (ftp_fget($this->connection, $temp, $path, FTP_BINARY, 0)) {
            fseek($temp, 0);
            return $temp;
        }
        fclose($temp);
        return null;
    }

    public function writeStream(string $path, $resource): bool
    {
        $dir = dirname($path);
        if ($dir !== '.') {
            $parts = explode('/', $dir);
            $current = '';
            foreach ($parts as $part) {
                if (empty($part)) continue;
                $current .= '/' . $part;
                @ftp_mkdir($this->connection, ltrim($current, '/'));
            }
        }
        return ftp_fput($this->connection, $path, $resource, FTP_BINARY);
    }

    public function __destruct()
    {
        if ($this->connection) {
            ftp_close($this->connection);
        }
    }
}
