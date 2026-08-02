<?php

namespace Framework\Core\Image\Drivers;

use Framework\Core\Image\Exceptions\ImageException;

class ImagickDriver extends AbstractDriver
{
    /** @var \Imagick|null */
    protected $resource = null;

    public function __construct()
    {
        if (!extension_loaded('imagick') || !class_exists('Imagick')) {
            throw ImageException::driverNotSupported('Imagick');
        }
    }

    public function load(string $data): self
    {
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

        try {
            $this->resource = new \Imagick();
            $this->resource->readImageBlob($data);
            
            // For multi-frame or GIF, select the first frame for general manipulations
            if ($this->resource->getNumberImages() > 1) {
                $this->resource = $this->resource->coalesceImages();
                $this->resource->setIteratorIndex(0);
            }

            $format = strtolower($this->resource->getImageFormat());
            $this->extension = ($format === 'jpeg') ? 'jpg' : $format;
            $this->mime = $this->mimeFromFormat($this->extension);
        } catch (\Exception $e) {
            throw ImageException::cannotLoadImage('binary input', $e->getMessage());
        }

        return $this;
    }

    public function create(int $width, int $height, string $backgroundColor = '#FFFFFF'): self
    {
        try {
            $this->resource = new \Imagick();
            $color = new \ImagickPixel($this->parseHexColor($backgroundColor)['hexa']);
            $this->resource->newImage(max(1, $width), max(1, $height), $color, 'png');
            $this->resource->setImageFormat('png');
            $this->mime = 'image/png';
            $this->extension = 'png';
        } catch (\Exception $e) {
            throw new ImageException("Failed to create blank Imagick canvas: " . $e->getMessage());
        }

        return $this;
    }

    public function getWidth(): int
    {
        return $this->resource ? $this->resource->getImageWidth() : 0;
    }

    public function getHeight(): int
    {
        return $this->resource ? $this->resource->getImageHeight() : 0;
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

        $this->resource->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1);
        $this->resource->setImagePage($width, $height, 0, 0);

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

        $this->resource->cropImage($width, $height, $x, $y);
        $this->resource->setImagePage($width, $height, 0, 0);

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

        $this->resize($newWidth, $newHeight, true, true);
        $coords = $this->calculatePosition($width, $height, $newWidth, $newHeight, $position);

        $canvas->getCore()->compositeImage($this->resource, \Imagick::COMPOSITE_OVER, $coords['x'], $coords['y']);
        $this->resource = $canvas->getCore();

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

    public function watermark($watermarkSource, string $position = 'bottom-right', int $offsetX = 10, int $offsetY = 10, int $opacity = 100, ?int $maxSizePercent = 20, float $angle = 0.0): self
    {
        if (!$watermarkSource instanceof ImageDriverInterface) {
            $watermark = \Framework\Core\Image\Image::load($watermarkSource, 'imagick');
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

        if (abs($angle) > 0.001) {
            $watermark->rotate($angle, '#00000000');
        }

        $wWidth = $watermark->getWidth();
        $wHeight = $watermark->getHeight();
        $coords = $this->calculatePosition($this->getWidth(), $this->getHeight(), $wWidth, $wHeight, $position, $offsetX, $offsetY);

        $wmCore = $watermark->getCore();
        if ($opacity < 100) {
            if (method_exists($wmCore, 'setImageOpacity')) {
                $wmCore->setImageOpacity($opacity / 100);
            } elseif (method_exists($wmCore, 'evaluateImage')) {
                $wmCore->evaluateImage(\Imagick::EVALUATE_MULTIPLY, $opacity / 100, \Imagick::CHANNEL_ALPHA);
            }
        }

        $this->resource->compositeImage($wmCore, \Imagick::COMPOSITE_OVER, $coords['x'], $coords['y']);

        return $this;
    }

    public function text(string $text, int $x = 0, int $y = 0, array $options = []): self
    {
        $font = $options['font'] ?? null;
        $size = (int) ($options['size'] ?? 16);
        $colorHex = $options['color'] ?? '#000000';
        $angle = (float) ($options['angle'] ?? 0);
        $position = $options['position'] ?? null;

        if ($font !== null && !file_exists($font)) {
            throw ImageException::fontNotFound($font);
        }

        $draw = new \ImagickDraw();
        if ($font) {
            $draw->setFont($font);
        }
        $draw->setFontSize($size);
        $color = $this->parseHexColor($colorHex);
        $draw->setFillColor(new \ImagickPixel($color['hexa']));

        if ($position !== null) {
            $metrics = $this->resource->queryFontMetrics($draw, $text);
            $tWidth = (int) round($metrics['textWidth']);
            $tHeight = (int) round($metrics['textHeight']);
            $coords = $this->calculatePosition($this->getWidth(), $this->getHeight(), $tWidth, $tHeight, $position, $x, $y);
            $x = $coords['x'];
            // AnnotateImage uses baseline for Y coordinate
            $y = $coords['y'] + (int) round($metrics['ascender']);
        } else {
            $metrics = $this->resource->queryFontMetrics($draw, $text);
            $y += (int) round($metrics['ascender']);
        }

        $this->resource->annotateImage($draw, $x, $y, $angle, $text);

        return $this;
    }

    public function rotate(float $angle, string $backgroundColor = '#00000000'): self
    {
        $color = $this->parseHexColor($backgroundColor);
        $bg = new \ImagickPixel($color['hexa']);
        $this->resource->rotateImage($bg, $angle);

        return $this;
    }

    public function flip(string $direction = 'horizontal'): self
    {
        $direction = strtolower(trim($direction));
        if ($direction === 'horizontal' || $direction === 'both') {
            $this->resource->flopImage(); // Flop is horizontal in Imagick
        }
        if ($direction === 'vertical' || $direction === 'both') {
            $this->resource->flipImage(); // Flip is vertical in Imagick
        }
        return $this;
    }

    public function filter(string $name, ...$args): self
    {
        $name = strtolower(trim($name));

        switch ($name) {
            case 'grayscale':
            case 'greyscale':
                $this->resource->modulateImage(100, 0, 100);
                break;
            case 'invert':
            case 'negative':
                $this->resource->negateImage(false);
                break;
            case 'brightness':
                $level = (int) ($args[0] ?? 0);
                $percentage = 100 + $level;
                $this->resource->modulateImage(max(0, $percentage), 100, 100);
                break;
            case 'contrast':
                $level = (int) ($args[0] ?? 0);
                $passes = abs((int) round($level / 10));
                for ($i = 0; $i < max(1, $passes); $i++) {
                    $this->resource->contrastImage($level > 0);
                }
                break;
            case 'blur':
                $passes = (float) ($args[0] ?? 1);
                $this->resource->gaussianBlurImage(0, max(0.5, $passes));
                break;
            case 'sharpen':
                $amount = (float) ($args[0] ?? 10);
                $this->resource->sharpenImage(0, max(0.5, $amount / 5));
                break;
            case 'pixelate':
                $blockSize = max(1, (int) ($args[0] ?? 10));
                $w = max(1, (int) round($this->getWidth() / $blockSize));
                $h = max(1, (int) round($this->getHeight() / $blockSize));
                $this->resource->resizeImage($w, $h, \Imagick::FILTER_POINT, 1);
                $this->resource->resizeImage($this->getWidth(), $this->getHeight(), \Imagick::FILTER_POINT, 1);
                break;
        }

        return $this;
    }

    public function encode(?string $format = null, int $quality = 90): string
    {
        $format = $this->deduceFormat($format);
        $quality = max(0, min(100, $quality));

        $imagickFormat = ($format === 'jpg' || $format === 'jpeg') ? 'jpeg' : $format;

        if ($imagickFormat === 'jpeg' && $this->resource->getImageAlphaChannel()) {
            // Merge onto solid white background for JPEGs
            $bg = new \Imagick();
            $bg->newImage($this->getWidth(), $this->getHeight(), new \ImagickPixel('#FFFFFF'), 'jpeg');
            $bg->compositeImage($this->resource, \Imagick::COMPOSITE_OVER, 0, 0);
            $this->resource = $bg;
        }

        $this->resource->setImageFormat($imagickFormat);
        $this->resource->setImageCompressionQuality($quality);

        return $this->resource->getImageBlob();
    }

    public function getCore()
    {
        return $this->resource;
    }
}
