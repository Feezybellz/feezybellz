<?php

namespace Framework\Core\Image\Drivers;

use Framework\Core\Image\Exceptions\ImageException;

class GdDriver extends AbstractDriver
{
    /** @var \GdImage|resource|null */
    protected $resource = null;

    public function __construct()
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw ImageException::driverNotSupported('GD');
        }
    }

    public function __destruct()
    {
        if (is_resource($this->resource)) {
            @imagedestroy($this->resource);
        }
    }

    public function load(string $data): self
    {
        // If file path is passed directly and exists on disk, read it
        if (strlen($data) < 2000 && @file_exists($data)) {
            $path = $data;
            $data = @file_get_contents($path);
            if ($data === false) {
                throw ImageException::cannotLoadImage($path, 'File read error.');
            }
        }

        if ($data === '') {
            throw ImageException::cannotLoadImage('empty string', 'No data provided.');
        }

        // Try getting metadata from binary stream
        $info = @getimagesizefromstring($data);
        if ($info !== false) {
            $this->mime = $info['mime'] ?? 'image/jpeg';
            $this->extension = $this->extensionFromMime($this->mime);
        }

        $resource = @imagecreatefromstring($data);
        if ($resource === false) {
            throw ImageException::cannotLoadImage('binary input', 'Invalid image format or corrupted data.');
        }

        $this->setResource($resource);

        return $this;
    }

    public function create(int $width, int $height, string $backgroundColor = '#FFFFFF'): self
    {
        $resource = imagecreatetruecolor(max(1, $width), max(1, $height));
        if ($resource === false) {
            throw new ImageException("Failed to create blank GD canvas ($width x $height).");
        }

        imagealphablending($resource, false);
        imagesavealpha($resource, true);

        $color = $this->parseHexColor($backgroundColor);
        $alloc = imagecolorallocatealpha($resource, $color['r'], $color['g'], $color['b'], $color['alpha_gd']);
        imagefill($resource, 0, 0, $alloc);

        imagealphablending($resource, true);
        
        $this->mime = 'image/png';
        $this->extension = 'png';
        $this->setResource($resource);

        return $this;
    }

    public function getWidth(): int
    {
        return $this->resource ? imagesx($this->resource) : 0;
    }

    public function getHeight(): int
    {
        return $this->resource ? imagesy($this->resource) : 0;
    }

    public function resize(int $width, ?int $height = null, bool $maintainAspectRatio = true, bool $upscale = false): self
    {
        $currentWidth = $this->getWidth();
        $currentHeight = $this->getHeight();

        if ($currentWidth === 0 || $currentHeight === 0) {
            return $this;
        }

        if ($height === null) {
            $height = (int) round(($currentHeight / $currentWidth) * $width);
        }

        if ($maintainAspectRatio) {
            $ratioWidth = $width / $currentWidth;
            $ratioHeight = $height / $currentHeight;
            $ratio = min($ratioWidth, $ratioHeight);

            if (!$upscale && $ratio > 1) {
                $ratio = 1;
            }

            $width = (int) round($currentWidth * $ratio);
            $height = (int) round($currentHeight * $ratio);
        } elseif (!$upscale) {
            $width = min($width, $currentWidth);
            $height = min($height, $currentHeight);
        }

        $width = max(1, $width);
        $height = max(1, $height);

        $newResource = $this->createTransparentCanvas($width, $height);
        imagecopyresampled($newResource, $this->resource, 0, 0, 0, 0, $width, $height, $currentWidth, $currentHeight);
        $this->setResource($newResource);

        return $this;
    }

    public function crop(int $width, int $height, ?int $x = null, ?int $y = null, string $position = 'center'): self
    {
        $currentWidth = $this->getWidth();
        $currentHeight = $this->getHeight();

        $width = min(max(1, $width), $currentWidth);
        $height = min(max(1, $height), $currentHeight);

        if ($x === null || $y === null) {
            $coords = $this->calculatePosition($currentWidth, $currentHeight, $width, $height, $position);
            $x = $x !== null ? $x : $coords['x'];
            $y = $y !== null ? $y : $coords['y'];
        }

        $newResource = $this->createTransparentCanvas($width, $height);
        imagecopyresampled($newResource, $this->resource, 0, 0, $x, $y, $width, $height, $width, $height);
        $this->setResource($newResource);

        return $this;
    }

    public function fit(int $width, int $height, string $position = 'center', string $backgroundColor = '#FFFFFF00'): self
    {
        $currentWidth = $this->getWidth();
        $currentHeight = $this->getHeight();

        $ratio = min($width / $currentWidth, $height / $currentHeight);
        $newWidth = (int) round($currentWidth * $ratio);
        $newHeight = (int) round($currentHeight * $ratio);

        $canvas = new self();
        $canvas->create($width, $height, $backgroundColor);

        $coords = $this->calculatePosition($width, $height, $newWidth, $newHeight, $position);

        imagealphablending($canvas->getCore(), true);
        imagecopyresampled($canvas->getCore(), $this->resource, $coords['x'], $coords['y'], 0, 0, $newWidth, $newHeight, $currentWidth, $currentHeight);

        $this->setResource($canvas->getCore());
        return $this;
    }

    public function cover(int $width, int $height, string $position = 'center'): self
    {
        $currentWidth = $this->getWidth();
        $currentHeight = $this->getHeight();

        $ratio = max($width / $currentWidth, $height / $currentHeight);
        $intermediateWidth = (int) ceil($currentWidth * $ratio);
        $intermediateHeight = (int) ceil($currentHeight * $ratio);

        $this->resize($intermediateWidth, $intermediateHeight, false, true);
        return $this->crop($width, $height, null, null, $position);
    }

    public function watermark($watermarkSource, string $position = 'bottom-right', int $offsetX = 10, int $offsetY = 10, int $opacity = 100, ?int $maxSizePercent = 20): self
    {
        if (!$watermarkSource instanceof ImageDriverInterface) {
            $watermark = \Framework\Core\Image\Image::load($watermarkSource, 'gd');
        } else {
            $watermark = clone $watermarkSource;
        }

        if ($maxSizePercent !== null && $maxSizePercent > 0) {
            $maxW = (int) round(($this->getWidth() * $maxSizePercent) / 100);
            $maxH = (int) round(($this->getHeight() * $maxSizePercent) / 100);
            if ($watermark->getWidth() > $maxW || $watermark->getHeight() > $maxH) {
                $watermark->resize($maxW, $maxH, true, false);
            }
        }

        $wWidth = $watermark->getWidth();
        $wHeight = $watermark->getHeight();
        $coords = $this->calculatePosition($this->getWidth(), $this->getHeight(), $wWidth, $wHeight, $position, $offsetX, $offsetY);

        imagealphablending($this->resource, true);

        if ($opacity >= 100) {
            imagecopy($this->resource, $watermark->getCore(), $coords['x'], $coords['y'], 0, 0, $wWidth, $wHeight);
        } else {
            // Apply opacity blending in GD while preserving alpha in watermark if possible
            $opacity = max(0, min(100, $opacity));
            $this->imageCopyMergeAlpha($this->resource, $watermark->getCore(), $coords['x'], $coords['y'], 0, 0, $wWidth, $wHeight, $opacity);
        }

        return $this;
    }

    public function text(string $text, int $x = 0, int $y = 0, array $options = []): self
    {
        $font = $options['font'] ?? null;
        $size = (int) ($options['size'] ?? 16);
        $colorHex = $options['color'] ?? '#000000';
        $angle = (float) ($options['angle'] ?? 0);
        $position = $options['position'] ?? null;

        $color = $this->parseHexColor($colorHex);
        $colorAlloc = imagecolorallocatealpha($this->resource, $color['r'], $color['g'], $color['b'], $color['alpha_gd']);

        imagealphablending($this->resource, true);

        if ($font && file_exists($font)) {
            // Compute bounding box
            $box = imagettfbbox($size, $angle, $font, $text);
            if ($box !== false) {
                $minX = min($box[0], $box[2], $box[4], $box[6]);
                $maxX = max($box[0], $box[2], $box[4], $box[6]);
                $minY = min($box[1], $box[3], $box[5], $box[7]);
                $maxY = max($box[1], $box[3], $box[5], $box[7]);
                $tWidth = $maxX - $minX;
                $tHeight = $maxY - $minY;

                if ($position !== null) {
                    $coords = $this->calculatePosition($this->getWidth(), $this->getHeight(), $tWidth, $tHeight, $position, $x, $y);
                    // For imagettftext, Y coordinate is bottom-left baseline
                    $x = $coords['x'] - $minX;
                    $y = $coords['y'] - $minY;
                } else {
                    $y += $tHeight;
                }
            }
            imagettftext($this->resource, $size, $angle, $x, $y, $colorAlloc, $font, $text);
        } else {
            if ($font !== null && !file_exists($font)) {
                throw ImageException::fontNotFound($font);
            }
            // Built-in GD bitmap font (1 to 5)
            $gdFont = max(1, min(5, (int) round($size / 4)));
            $tWidth = imagefontwidth($gdFont) * strlen($text);
            $tHeight = imagefontheight($gdFont);

            if ($position !== null) {
                $coords = $this->calculatePosition($this->getWidth(), $this->getHeight(), $tWidth, $tHeight, $position, $x, $y);
                $x = $coords['x'];
                $y = $coords['y'];
            }

            imagestring($this->resource, $gdFont, $x, $y, $text, $colorAlloc);
        }

        return $this;
    }

    public function rotate(float $angle, string $backgroundColor = '#00000000'): self
    {
        $color = $this->parseHexColor($backgroundColor);
        $bgAlloc = imagecolorallocatealpha($this->resource, $color['r'], $color['g'], $color['b'], $color['alpha_gd']);

        // In GD, positive degrees rotate counter-clockwise; negate for standard clockwise rotation
        $rotated = imagerotate($this->resource, -$angle, $bgAlloc);
        if ($rotated !== false) {
            imagealphablending($rotated, false);
            imagesavealpha($rotated, true);
            $this->setResource($rotated);
        }

        return $this;
    }

    public function flip(string $direction = 'horizontal'): self
    {
        $direction = strtolower(trim($direction));
        if ($direction === 'horizontal') {
            $mode = IMG_FLIP_HORIZONTAL;
        } elseif ($direction === 'vertical') {
            $mode = IMG_FLIP_VERTICAL;
        } else {
            $mode = IMG_FLIP_BOTH;
        }

        if (function_exists('imageflip')) {
            imageflip($this->resource, $mode);
        }
        return $this;
    }

    public function filter(string $name, ...$args): self
    {
        $name = strtolower(trim($name));

        switch ($name) {
            case 'grayscale':
            case 'greyscale':
                imagefilter($this->resource, IMG_FILTER_GRAYSCALE);
                break;
            case 'invert':
            case 'negative':
                imagefilter($this->resource, IMG_FILTER_NEGATE);
                break;
            case 'brightness':
                $level = (int) ($args[0] ?? 0);
                imagefilter($this->resource, IMG_FILTER_BRIGHTNESS, max(-255, min(255, $level)));
                break;
            case 'contrast':
                $level = (int) ($args[0] ?? 0);
                // GD contrast is reversed (-100 is higher contrast, 100 is lower)
                imagefilter($this->resource, IMG_FILTER_CONTRAST, -1 * max(-100, min(100, $level)));
                break;
            case 'blur':
                $passes = (int) ($args[0] ?? 1);
                for ($i = 0; $i < max(1, $passes); $i++) {
                    imagefilter($this->resource, IMG_FILTER_GAUSSIAN_BLUR);
                }
                break;
            case 'sharpen':
                $amount = (int) ($args[0] ?? 10);
                // Simple 3x3 sharpen matrix
                $val = 8 + ($amount / 10);
                $matrix = [
                    [-1, -1, -1],
                    [-1, $val, -1],
                    [-1, -1, -1]
                ];
                $divisor = array_sum(array_map('array_sum', $matrix));
                imageconvolution($this->resource, $matrix, $divisor ?: 1, 0);
                break;
            case 'pixelate':
                $blockSize = max(1, (int) ($args[0] ?? 10));
                imagefilter($this->resource, IMG_FILTER_PIXELATE, $blockSize, true);
                break;
        }

        return $this;
    }

    public function encode(?string $format = null, int $quality = 90): string
    {
        $format = $this->deduceFormat($format);
        $quality = max(0, min(100, $quality));

        ob_start();

        switch ($format) {
            case 'png':
                imagealphablending($this->resource, false);
                imagesavealpha($this->resource, true);
                $pngQuality = (int) round((100 - $quality) / 10);
                $pngQuality = max(0, min(9, $pngQuality));
                imagepng($this->resource, null, $pngQuality);
                break;
            case 'webp':
                if (!function_exists('imagewebp')) {
                    ob_end_clean();
                    throw ImageException::unsupportedFormat('webp');
                }
                imagealphablending($this->resource, false);
                imagesavealpha($this->resource, true);
                imagewebp($this->resource, null, $quality);
                break;
            case 'gif':
                imagegif($this->resource, null);
                break;
            case 'avif':
                if (!function_exists('imageavif')) {
                    ob_end_clean();
                    throw ImageException::unsupportedFormat('avif');
                }
                imagealphablending($this->resource, false);
                imagesavealpha($this->resource, true);
                imageavif($this->resource, null, $quality);
                break;
            case 'jpg':
            case 'jpeg':
            default:
                // Create white background for transparent images converted to JPEG
                $w = $this->getWidth();
                $h = $this->getHeight();
                $bg = imagecreatetruecolor($w, $h);
                $white = imagecolorallocate($bg, 255, 255, 255);
                imagefill($bg, 0, 0, $white);
                imagecopy($bg, $this->resource, 0, 0, 0, 0, $w, $h);
                imagejpeg($bg, null, $quality);
                imagedestroy($bg);
                break;
        }

        return (string) ob_get_clean();
    }

    public function getCore()
    {
        return $this->resource;
    }

    protected function setResource($resource): void
    {
        if (is_resource($this->resource)) {
            @imagedestroy($this->resource);
        }
        $this->resource = $resource;
    }

    protected function createTransparentCanvas(int $width, int $height)
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagealphablending($canvas, true);
        return $canvas;
    }

    /**
     * Helper to preserve alpha when merging layers with < 100% opacity in GD.
     */
    protected function imageCopyMergeAlpha($dst, $src, int $dstX, int $dstY, int $srcX, int $srcY, int $srcW, int $srcH, int $pct): void
    {
        $cut = imagecreatetruecolor($srcW, $srcH);
        imagecopy($cut, $dst, 0, 0, $dstX, $dstY, $srcW, $srcH);
        imagecopy($cut, $src, 0, 0, $srcX, $srcY, $srcW, $srcH);
        imagecopymerge($dst, $cut, $dstX, $dstY, 0, 0, $srcW, $srcH, $pct);
        imagedestroy($cut);
    }
}
