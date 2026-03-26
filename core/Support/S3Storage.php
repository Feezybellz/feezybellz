<?php

namespace Framework\Core\Support;

use Framework\Core\Http\UploadedFile;

class S3Storage
{
    /**
     * Upload a file and return only the KEY (path)
     * 
     * @param UploadedFile $file
     * @param string $directory
     * @return string|null The relative path (key) to be stored in the DB
     */
    public static function upload(UploadedFile $file, string $directory): ?string
    {
        if (!$file->isValid()) return null;

        $fileName = bin2hex(random_bytes(16)) . '.' . $file->getClientOriginalExtension();
        $key = trim($directory, '/') . '/' . $fileName;

        // Implementation for S3/MinIO upload logic (e.g., PutObject)
        // For zero-dependency, use CURL or SDK if available.

        return $key;
    }

    /**
     * Generate the full URL for a given key
     * Supports S3 and MinIO (via environment variables)
     * 
     * @param string|null $key
     * @return string|null
     */
    public static function buildUrl(?string $key): ?string
    {
        if (!$key) return null;

        $bucket = env('AWS_BUCKET');
        $endpoint = env('AWS_ENDPOINT'); // Use this for MinIO (e.g., http://localhost:9000)
        
        if ($endpoint) {
            return rtrim($endpoint, '/') . "/{$bucket}/" . ltrim($key, '/');
        }

        // Default S3 URL structure
        $region = env('AWS_DEFAULT_REGION', 'us-east-1');
        return "https://{$bucket}.s3.{$region}.amazonaws.com/" . ltrim($key, '/');
    }
}
