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

    public function __destruct()
    {
        if ($this->connection) {
            ftp_close($this->connection);
        }
    }
}
