<?php

namespace Framework\Core\Image\Exceptions;

use Exception;

class ImageException extends Exception
{
    public static function driverNotSupported(string $driver): self
    {
        return new self("Image driver [{$driver}] is neither installed nor supported by your PHP environment.");
    }

    public static function cannotLoadImage(string $source, string $reason = ''): self
    {
        $message = "Cannot load image from source [{$source}].";
        if ($reason !== '') {
            $message .= " Reason: {$reason}";
        }
        return new self($message);
    }

    public static function unsupportedFormat(string $format): self
    {
        return new self("Image format [{$format}] is not supported by the active driver.");
    }

    public static function fontNotFound(string $path): self
    {
        return new self("TrueType Font file not found at [{$path}].");
    }
}
