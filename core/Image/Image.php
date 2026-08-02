<?php

namespace Framework\Core\Image;

use Framework\Core\Image\Drivers\ImageDriverInterface;
use Framework\Core\Image\Drivers\GdDriver;
use Framework\Core\Image\Drivers\ImagickDriver;
use Framework\Core\Image\Exceptions\ImageException;
use Framework\Core\Storage\Storage;

class Image
{
    /** @var string Default driver: 'auto', 'imagick', or 'gd' */
    protected static string $defaultDriver = 'auto';

    /**
     * Set the preferred image driver to use globally ('auto', 'imagick', 'gd').
     */
    public static function useDriver(string $driver): void
    {
        self::$defaultDriver = strtolower(trim($driver));
    }

    /**
     * Get the currently configured default driver name.
     */
    public static function getDefaultDriver(): string
    {
        // Check framework configuration if available and default hasn't been explicitly altered
        if (self::$defaultDriver === 'auto' && function_exists('config')) {
            $configDriver = config('image.driver');
            if (!empty($configDriver)) {
                return strtolower(trim($configDriver));
            }
        }
        return self::$defaultDriver;
    }

    /**
     * Load an image from a diverse array of potential sources:
     * - $_FILES upload array (e.g. $_FILES['avatar'])
     * - Remote HTTP / HTTPS URL
     * - Local filesystem file path
     * - Raw image binary string
     * - Another driver instance or file object
     *
     * @param mixed $source
     * @param string|null $driver Override driver ('imagick' or 'gd')
     * @return ImageDriverInterface
     * @throws ImageException
     */
    public static function load($source, ?string $driver = null): ImageDriverInterface
    {
        $engine = self::makeDriver($driver);
        $data = self::resolveSourceToBinary($source);

        return $engine->load($data);
    }

    /**
     * Load an image directly from a configured framework Storage disk.
     *
     * @param string $diskName Storage disk name (e.g. 's3', 'local', 'r2')
     * @param string $path Path within disk
     * @param string|null $driver Override driver
     * @return ImageDriverInterface
     * @throws ImageException
     */
    public static function fromDisk(string $diskName, string $path, ?string $driver = null): ImageDriverInterface
    {
        $contents = Storage::disk($diskName)->get($path);
        if ($contents === null || $contents === '') {
            throw ImageException::cannotLoadImage("disk:[{$diskName}] {$path}", "File not found or empty on storage disk.");
        }

        return self::load($contents, $driver);
    }

    /**
     * Create a blank transparent or solid canvas.
     *
     * @param int $width Width in pixels
     * @param int $height Height in pixels
     * @param string $backgroundColor Hex color string (#FFFFFF or #00000000 for transparent)
     * @param string|null $driver Override driver
     * @return ImageDriverInterface
     */
    public static function create(int $width, int $height, string $backgroundColor = '#FFFFFF', ?string $driver = null): ImageDriverInterface
    {
        $engine = self::makeDriver($driver);
        return $engine->create($width, $height, $backgroundColor);
    }

    /**
     * Instantiate the appropriate image driver engine.
     */
    public static function makeDriver(?string $driver = null): ImageDriverInterface
    {
        $target = $driver ? strtolower(trim($driver)) : self::getDefaultDriver();

        if ($target === 'auto') {
            if (extension_loaded('imagick') && class_exists('Imagick')) {
                try {
                    return new ImagickDriver();
                } catch (\Exception $e) {
                    // Fall back gracefully to GD if Imagick fails initialization
                }
            }
            return new GdDriver();
        }

        if ($target === 'imagick') {
            return new ImagickDriver();
        }

        if ($target === 'gd') {
            return new GdDriver();
        }

        throw ImageException::driverNotSupported($target);
    }

    /**
     * Convert an arbitrary input source into raw image binary data or a valid file path.
     */
    protected static function resolveSourceToBinary($source): string
    {
        // 1. Handle $_FILES array
        if (is_array($source)) {
            if (isset($source['error']) && $source['error'] !== UPLOAD_ERR_OK) {
                throw ImageException::cannotLoadImage('$_FILES array', "Upload error code [{$source['error']}].");
            }
            if (!empty($source['tmp_name']) && is_string($source['tmp_name'])) {
                $content = @file_get_contents($source['tmp_name']);
                if ($content === false) {
                    throw ImageException::cannotLoadImage($source['tmp_name'], 'Failed to read uploaded temporary file.');
                }
                return $content;
            }
            throw ImageException::cannotLoadImage('array', 'Malformed $_FILES array structure.');
        }

        // 2. Handle object with string representation (e.g. SplFileInfo, custom File instances)
        if (is_object($source)) {
            if (method_exists($source, 'getRealPath')) {
                $path = $source->getRealPath();
                return self::resolveSourceToBinary($path);
            }
            if (method_exists($source, '__toString')) {
                $source = (string) $source;
            } else {
                throw ImageException::cannotLoadImage(get_class($source), 'Object cannot be converted to an image stream.');
            }
        }

        // 3. Handle string (URL, file path, or raw binary)
        if (is_string($source)) {
            if ($source === '') {
                throw ImageException::cannotLoadImage('empty string', 'Source string is empty.');
            }

            // Check if remote URL
            if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
                return self::fetchRemoteUrl($source);
            }

            // Check if local file on disk (only check if string length looks like a path and not multi-megabyte binary data)
            if (strlen($source) < 2000 && @file_exists($source) && is_file($source)) {
                $content = @file_get_contents($source);
                if ($content === false) {
                    throw ImageException::cannotLoadImage($source, 'Permission denied reading file on disk.');
                }
                return $content;
            }

            // Otherwise treated as raw binary image data
            return $source;
        }

        throw ImageException::cannotLoadImage('unknown type', 'Unsupported source input data type.');
    }

    /**
     * Safely fetch a remote image URL using cURL or file_get_contents fallback.
     */
    protected static function fetchRemoteUrl(string $url): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'Framework-Image-Service/1.0',
            ]);
            $content = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($content !== false && $httpCode >= 200 && $httpCode < 300) {
                return $content;
            }

            if ($content === false && $error !== '') {
                throw ImageException::cannotLoadImage($url, "cURL failed: {$error}");
            }
        }

        // Fallback to stream context if cURL not available or failed without explicit curl exception
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 10,
                'follow_location' => 1,
                'max_redirects' => 3,
                'header' => "User-Agent: Framework-Image-Service/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ]
        ]);

        $content = @file_get_contents($url, false, $ctx);
        if ($content === false) {
            throw ImageException::cannotLoadImage($url, "Failed to download remote image stream.");
        }

        return $content;
    }
}
