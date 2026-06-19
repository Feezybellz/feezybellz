<?php

namespace Framework\Core\Storage\Drivers;

interface StorageDriverInterface
{
    /**
     * Write the contents of a file.
     */
    public function put(string $path, $contents): bool;

    /**
     * Get the contents of a file.
     */
    public function get(string $path): ?string;

    /**
     * Determine if a file exists.
     */
    public function exists(string $path): bool;

    /**
     * Delete the file at a given path.
     */
    public function delete(string $path): bool;

    /**
     * Get the URL for the file at the given path.
     */
    public function url(string $path): string;

    /**
     * Get a temporary, pre-signed URL for a file.
     */
    public function temporaryUrl(string $path, \DateTimeInterface $expiration, array $options = []): string;

    /**
     * Get a temporary, pre-signed URL to upload a file.
     */
    public function temporaryUploadUrl(string $path, \DateTimeInterface $expiration, array $options = []): array;
