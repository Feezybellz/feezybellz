<?php

namespace Framework\Core\Storage\Drivers;

class S3Driver implements StorageDriverInterface
{
    protected $client;
    protected $bucket;
    protected $url;

    public function __construct(array $config)
    {
        if (!class_exists('\Aws\S3\S3Client')) {
            throw new \Exception("To use the S3 driver, you must install the AWS SDK: composer require aws/aws-sdk-php");
        }

        $this->bucket = $config['bucket'];
        $this->url = $config['url'] ?? null;

        $clientConfig = [
            'region' => $config['region'],
            'version' => 'latest',
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
        ];

        if (!empty($config['endpoint'])) {
            $clientConfig['endpoint'] = $config['endpoint'];
            $clientConfig['use_path_style_endpoint'] = true;
        }

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
}
