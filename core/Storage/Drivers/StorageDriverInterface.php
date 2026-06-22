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

    /**
     * Move a file to a new location.
     */
    public function move(string $from, string $to): bool;

    /**
     * Copy a file to a new location.
     */
    public function copy(string $from, string $to): bool;

    /**
     * Get the file size of a given file in bytes.
     */
    public function size(string $path): int;

    /**
     * Get the file's last modification time.
     */
    public function lastModified(string $path): int;

    /**
     * Get the mime-type of a given file.
     * @return string|false
     */
    public function mimeType(string $path);

    /**
     * Get an array of all files in a directory.
     */
    public function files(string $directory): array;

    /**
     * Get all of the directories within a given directory.
     */
    public function directories(string $directory): array;

    /**
     * Recursively delete a directory.
     */
    public function deleteDirectory(string $directory): bool;

    /**
     * Read a file as a stream.
     * @return resource|null
     */
    public function readStream(string $path);

    /**
     * Write a new file using a stream.
     * @param resource $resource
     */
    public function writeStream(string $path, $resource): bool;
}
