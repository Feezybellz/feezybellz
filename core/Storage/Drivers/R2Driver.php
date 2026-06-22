<?php

namespace Framework\Core\Storage\Drivers;

class R2Driver implements StorageDriverInterface
{
    protected $client;
    protected $bucket;
    protected $url;

    public function __construct(array $config)
    {
        if (!class_exists('\Aws\S3\S3Client')) {
            throw new \Exception("To use the Cloudflare R2 driver, you must install the AWS SDK: composer require aws/aws-sdk-php");
        }

        $this->bucket = $config['bucket'];
        $this->url = $config['url'] ?? null;

        $clientConfig = [
            'region' => $config['region'] ?? 'auto',
            'version' => 'latest',
            'endpoint' => $config['endpoint'],
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
            'use_path_style_endpoint' => true,
        ];

        $this->client = new \Aws\S3\S3Client($clientConfig);
    }

    public function put(string $path, $contents): bool
    {
        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $path,
                'Body' => $contents,
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function get(string $path): ?string
    {
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $path,
            ]);
            return (string) $result['Body'];
        } catch (\Exception $e) {
            return null;
        }
    }

    public function exists(string $path): bool
    {
        return $this->client->doesObjectExist($this->bucket, $path);
    }

    public function delete(string $path): bool
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $path,
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function url(string $path): string
    {
        if ($this->url) {
            return rtrim($this->url, '/') . '/' . ltrim($path, '/');
        }

        // R2 doesn't have a predictable public URL format by default like S3, 
        // usually users map a custom domain (which should be set in config['url']).
        return $this->client->getObjectUrl($this->bucket, $path);
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
    {
        $command = $this->client->getCommand('GetObject', array_merge([
            'Bucket' => $this->bucket,
            'Key' => $path,
        ], $options));

        return (string) $this->client->createPresignedRequest($command, $expiration)->getUri();
    }

    public function temporaryUploadUrl(string $path, \DateTimeInterface $expiration, array $options = []): array
    {
        $command = $this->client->getCommand('PutObject', array_merge([
            'Bucket' => $this->bucket,
            'Key' => $path,
        ], $options));

        $request = $this->client->createPresignedRequest($command, $expiration);

        return [
            'url' => (string) $request->getUri(),
            'headers' => $request->getHeaders(),
        ];
    }

    public function move(string $from, string $to): bool
    {
        try {
            $this->client->copyObject([
                'Bucket' => $this->bucket,
                'CopySource' => "{$this->bucket}/{$from}",
                'Key' => $to,
            ]);
            
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $from,
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function copy(string $from, string $to): bool
    {
        try {
            $this->client->copyObject([
                'Bucket' => $this->bucket,
                'CopySource' => "{$this->bucket}/{$from}",
                'Key' => $to,
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function size(string $path): int
    {
        try {
            $head = $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $path]);
            return (int) $head['ContentLength'];
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function lastModified(string $path): int
    {
        try {
            $head = $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $path]);
            return strtotime($head['LastModified']);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function mimeType(string $path)
    {
        try {
            $head = $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $path]);
            return $head['ContentType'];
        } catch (\Exception $e) {
            return false;
        }
    }

    public function files(string $directory): array
    {
        $directory = rtrim($directory, '/') . '/';
        try {
            $results = $this->client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $directory,
                'Delimiter' => '/'
            ]);
            $files = [];
            if (isset($results['Contents'])) {
                foreach ($results['Contents'] as $object) {
                    if ($object['Key'] !== $directory) {
                        $files[] = $object['Key'];
                    }
                }
            }
            return $files;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function directories(string $directory): array
    {
        $directory = rtrim($directory, '/') . '/';
        try {
            $results = $this->client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $directory,
                'Delimiter' => '/'
            ]);
            $dirs = [];
            if (isset($results['CommonPrefixes'])) {
                foreach ($results['CommonPrefixes'] as $prefix) {
                    $dirs[] = rtrim($prefix['Prefix'], '/');
                }
            }
            return $dirs;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function deleteDirectory(string $directory): bool
    {
        $directory = rtrim($directory, '/') . '/';
        try {
            $results = $this->client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $directory,
            ]);
            
            if (empty($results['Contents'])) return true;
            
            $objects = array_map(function($object) {
                return ['Key' => $object['Key']];
            }, $results['Contents']);
            
            $this->client->deleteObjects([
                'Bucket' => $this->bucket,
                'Delete' => ['Objects' => $objects],
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function readStream(string $path)
    {
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $path,
            ]);
            return $result['Body']->detach();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function writeStream(string $path, $resource): bool
    {
        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $path,
                'Body' => $resource,
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
