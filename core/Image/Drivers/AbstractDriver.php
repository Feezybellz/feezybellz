<?php

namespace Framework\Core\Image\Drivers;

use Framework\Core\Storage\Storage;
use Framework\Core\Image\Exceptions\ImageException;

abstract class AbstractDriver implements ImageDriverInterface
{
    protected string $mime = 'image/jpeg';
    protected string $extension = 'jpg';

    public function getMime(): string
    {
        return $this->mime;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function thumbnail(int $size, string $mode = 'cover'): self
    {
        if (strtolower($mode) === 'fit') {
            return $this->fit($size, $size, 'center', '#FFFFFF00');
        }
        return $this->cover($size, $size, 'center');
    }

    public function grayscale(): self
    {
        return $this->filter('grayscale');
    }

    public function invert(): self
    {
        return $this->filter('invert');
    }

    public function brightness(int $level): self
    {
        return $this->filter('brightness', $level);
    }

    public function contrast(int $level): self
    {
        return $this->filter('contrast', $level);
    }

    public function blur(int $passes = 1): self
    {
        return $this->filter('blur', $passes);
    }

    public function sharpen(int $amount = 10): self
    {
        return $this->filter('sharpen', $amount);
    }

    public function pixelate(int $blockSize = 10): self
    {
        return $this->filter('pixelate', $blockSize);
    }

    public function save(string $path, ?string $format = null, int $quality = 90): self
    {
        $format = $this->deduceFormat($format, $path);
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $data = $this->encode($format, $quality);
        if (file_put_contents($path, $data) === false) {
            throw new ImageException("Failed writing image file to [{$path}]. Check permissions.");
        }

        return $this;
    }

    public function saveToDisk(string $diskName, string $path, ?string $format = null, int $quality = 90): self
    {
        $format = $this->deduceFormat($format, $path);
        $data = $this->encode($format, $quality);

        Storage::disk($diskName)->put($path, $data);

        return $this;
    }

    public function toDataUri(?string $format = null, int $quality = 90): string
    {
        $format = $this->deduceFormat($format);
        $data = $this->encode($format, $quality);
        $mime = $this->mimeFromFormat($format);

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    public function response(?string $format = null, int $quality = 90): void
    {
        $format = $this->deduceFormat($format);
        $data = $this->encode($format, $quality);
        $mime = $this->mimeFromFormat($format);

        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . strlen($data));
        }

        echo $data;
    }

    public function toBinary(?string $format = null, int $quality = 90): string
    {
        return $this->encode($format, $quality);
    }

    public function toStream(?string $format = null, int $quality = 90)
    {
        $data = $this->encode($format, $quality);
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new ImageException("Failed to allocate PHP memory stream resource.");
        }
        fwrite($stream, $data);
        rewind($stream);

        return $stream;
    }

    public function pipe(callable $callback, ...$args)
    {
        return $callback($this, ...$args);
    }

    public function export(callable $callback, ?string $format = null, int $quality = 90)
    {
        $format = $this->deduceFormat($format);
        $data = $this->encode($format, $quality);
        $mime = $this->mimeFromFormat($format);

        return $callback($data, $mime, $this);
    }

    /**
     * Deduce file format from explicit argument or path extension, falling back to current extension.
     */
    protected function deduceFormat(?string $format = null, ?string $path = null): string
    {
        if ($format !== null && $format !== '') {
            return strtolower(ltrim($format, '.'));
        }

        if ($path !== null && $path !== '') {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext !== '') {
                return $ext;
            }
        }

        return $this->extension ?: 'jpg';
    }

    /**
     * Map file format to Mime type.
     */
    protected function mimeFromFormat(string $format): string
    {
        $format = strtolower($format);
        $map = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
            'avif' => 'image/avif',
            'bmp'  => 'image/bmp',
            'ico'  => 'image/x-icon',
            'tiff' => 'image/tiff',
            'tif'  => 'image/tiff',
        ];

        return $map[$format] ?? 'image/jpeg';
    }

    /**
     * Map Mime type to default extension.
     */
    protected function extensionFromMime(string $mime): string
    {
        $mime = strtolower(trim($mime));
        $map = [
            'image/jpeg'    => 'jpg',
            'image/png'     => 'png',
            'image/webp'    => 'webp',
            'image/gif'     => 'gif',
            'image/avif'    => 'avif',
            'image/bmp'     => 'bmp',
            'image/x-icon'  => 'ico',
            'image/tiff'    => 'tiff',
        ];

        return $map[$mime] ?? 'jpg';
    }

    /**
     * Parse a hex color string into RGBA values.
     * Return array: ['r' => int, 'g' => int, 'b' => int, 'alpha_gd' => int (0-127), 'alpha_float' => float (0.0-1.0), 'hex' => string]
     */
    protected function parseHexColor(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
            $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
            $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
            $a = 255;
        } elseif (strlen($hex) === 4) {
            $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
            $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
            $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
            $a = hexdec(str_repeat(substr($hex, 3, 1), 2));
        } elseif (strlen($hex) === 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $a = 255;
        } elseif (strlen($hex) === 8) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $a = hexdec(substr($hex, 6, 2));
        } else {
            // Fallback to solid black if unparseable
            $r = 0; $g = 0; $b = 0; $a = 255;
        }

        // GD alpha ranges from 0 (opaque) to 127 (transparent)
        $alphaGd = (int) max(0, min(127, round((255 - $a) * 127 / 255)));
        // Float alpha from 0.0 (transparent) to 1.0 (opaque)
        $alphaFloat = round($a / 255, 2);

        return [
            'r'           => (int) $r,
            'g'           => (int) $g,
            'b'           => (int) $b,
            'alpha_gd'    => $alphaGd,
            'alpha_float' => $alphaFloat,
            'hex'         => sprintf("#%02X%02X%02X", $r, $g, $b),
            'hexa'        => sprintf("#%02X%02X%02X%02X", $r, $g, $b, $a),
        ];
    }

    /**
     * Calculate top-left x,y coordinate based on positional keywords and offsets.
     */
    protected function calculatePosition(
        int $containerWidth,
        int $containerHeight,
        int $itemWidth,
        int $itemHeight,
        string $position = 'center',
        int $offsetX = 0,
        int $offsetY = 0
    ): array {
        $position = strtolower(str_replace('_', '-', $position));

        switch ($position) {
            case 'top-left':
                $x = $offsetX;
                $y = $offsetY;
                break;
            case 'top':
            case 'top-center':
                $x = (int) (($containerWidth - $itemWidth) / 2) + $offsetX;
                $y = $offsetY;
                break;
            case 'top-right':
                $x = $containerWidth - $itemWidth - $offsetX;
                $y = $offsetY;
                break;
            case 'left':
            case 'left-center':
                $x = $offsetX;
                $y = (int) (($containerHeight - $itemHeight) / 2) + $offsetY;
                break;
            case 'right':
            case 'right-center':
                $x = $containerWidth - $itemWidth - $offsetX;
                $y = (int) (($containerHeight - $itemHeight) / 2) + $offsetY;
                break;
            case 'bottom-left':
                $x = $offsetX;
                $y = $containerHeight - $itemHeight - $offsetY;
                break;
            case 'bottom':
            case 'bottom-center':
                $x = (int) (($containerWidth - $itemWidth) / 2) + $offsetX;
                $y = $containerHeight - $itemHeight - $offsetY;
                break;
            case 'bottom-right':
                $x = $containerWidth - $itemWidth - $offsetX;
                $y = $containerHeight - $itemHeight - $offsetY;
                break;
            case 'center':
            default:
                $x = (int) (($containerWidth - $itemWidth) / 2) + $offsetX;
                $y = (int) (($containerHeight - $itemHeight) / 2) + $offsetY;
                break;
        }

        return ['x' => $x, 'y' => $y];
    }
}
