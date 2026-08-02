<?php

namespace Framework\Core\Image\Drivers;

interface ImageDriverInterface
{
    /**
     * Load an image from raw binary data or filepath.
     *
     * @param string $data Raw binary data or file path
     * @return self
     */
    public function load(string $data): self;

    /**
     * Create a new blank canvas.
     *
     * @param int $width
     * @param int $height
     * @param string $backgroundColor Hex color (e.g. #FFFFFF or #00000000 for transparent)
     * @return self
     */
    public function create(int $width, int $height, string $backgroundColor = '#FFFFFF'): self;

    /**
     * Get image width in pixels.
     */
    public function getWidth(): int;

    /**
     * Get image height in pixels.
     */
    public function getHeight(): int;

    /**
     * Get detected mime type (e.g. image/jpeg, image/png).
     */
    public function getMime(): string;

    /**
     * Get standard file extension (e.g. jpg, png, webp).
     */
    public function getExtension(): string;

    /**
     * Resize image.
     *
     * @param int $width Target width
     * @param int|null $height Target height (calculated automatically if null when maintaining aspect ratio)
     * @param bool $maintainAspectRatio Keep proportions intact
     * @param bool $upscale Allow enlarging image beyond original dimensions
     * @return self
     */
    public function resize(int $width, ?int $height = null, bool $maintainAspectRatio = true, bool $upscale = false): self;

    /**
     * Crop image to specific dimensions from coordinates or anchor position.
     *
     * @param int $width
     * @param int $height
     * @param int|null $x Top-left X coordinate (or computed via $position if null)
     * @param int|null $y Top-left Y coordinate (or computed via $position if null)
     * @param string $position Alignment ('center', 'top-left', 'top-right', 'bottom-left', 'bottom-right', etc.)
     * @return self
     */
    public function crop(int $width, int $height, ?int $x = null, ?int $y = null, string $position = 'center'): self;

    /**
     * Fit image into bounding box without clipping, optionally adding padded background.
     *
     * @param int $width
     * @param int $height
     * @param string $position
     * @param string $backgroundColor
     * @return self
     */
    public function fit(int $width, int $height, string $position = 'center', string $backgroundColor = '#FFFFFF00'): self;

    /**
     * Resize and crop image to completely cover the specified box (ideal for avatars/thumbnails).
     *
     * @param int $width
     * @param int $height
     * @param string $position
     * @return self
     */
    public function cover(int $width, int $height, string $position = 'center'): self;

    /**
     * Quick helper to create square thumbnail.
     *
     * @param int $size
     * @param string $mode ('cover' or 'fit')
     * @return self
     */
    public function thumbnail(int $size, string $mode = 'cover'): self;

    /**
     * Add an image logo watermark over the current image.
     *
     * @param mixed $watermarkSource Image source path, binary data, or another Image Driver instance
     * @param string $position Position ('bottom-right', 'bottom-left', 'top-right', 'top-left', 'center', etc.)
     * @param int $offsetX Horizontal offset padding in pixels
     * @param int $offsetY Vertical offset padding in pixels
     * @param int $opacity Opacity percentage (0-100)
     * @param int|null $maxSizePercent Max percentage of target image width/height the watermark should consume
     * @param float $angle Rotation angle in degrees for the watermark
     * @return self
     */
    public function watermark($watermarkSource, string $position = 'bottom-right', int $offsetX = 10, int $offsetY = 10, int $opacity = 100, ?int $maxSizePercent = 20, float $angle = 0.0): self;

    /**
     * Write text watermark/overlay onto the image.
     *
     * @param string $text
     * @param int $x X coordinates or computed offset
     * @param int $y Y coordinates or computed offset
     * @param array $options Options: ['font' => 'path/to.ttf', 'size' => 16, 'color' => '#000000', 'angle' => 0, 'position' => 'bottom-right', 'opacity' => 100]
     * @return self
     */
    public function text(string $text, int $x = 0, int $y = 0, array $options = []): self;

    /**
     * Rotate the image by a specific angle in degrees.
     *
     * @param float $angle Degrees to rotate
     * @param string $backgroundColor Background fill for exposed areas (#RRGGBB or #RRGGBBAA)
     * @return self
     */
    public function rotate(float $angle, string $backgroundColor = '#00000000'): self;

    /**
     * Flip the image direction.
     *
     * @param string $direction 'horizontal', 'vertical', or 'both'
     * @return self
     */
    public function flip(string $direction = 'horizontal'): self;

    /**
     * Apply a visual filter or color alteration.
     *
     * @param string $name 'grayscale', 'invert', 'brightness', 'contrast', 'blur', 'sharpen', 'pixelate'
     * @param mixed ...$args Additional arguments (e.g. intensity level for brightness/blur)
     * @return self
     */
    public function filter(string $name, ...$args): self;

    /**
     * Sugar methods for common filters.
     */
    public function grayscale(): self;
    public function invert(): self;
    public function brightness(int $level): self; // -100 to 100
    public function contrast(int $level): self;   // -100 to 100
    public function blur(int $passes = 1): self;
    public function sharpen(int $amount = 10): self;
    public function pixelate(int $blockSize = 10): self;

    /**
     * Encode image into raw binary stream in specified format and quality.
     *
     * @param string|null $format Target format ('jpg', 'jpeg', 'png', 'webp', 'gif'). If null, uses original format.
     * @param int $quality Compression quality (0-100)
     * @return string Raw image binary string
     */
    public function encode(?string $format = null, int $quality = 90): string;

    /**
     * Save the image to the local filesystem. Automatically creates parent directory if missing.
     *
     * @param string $path File destination path
     * @param string|null $format Override output format, or deduce from file extension
     * @param int $quality Compression quality (0-100)
     * @return self
     */
    public function save(string $path, ?string $format = null, int $quality = 90): self;

    /**
     * Save the image directly to a configured framework Storage disk (e.g., local, s3, r2, ftp).
     *
     * @param string $diskName Storage disk name
     * @param string $path Target path inside disk
     * @param string|null $format Override output format, or deduce from file extension
     * @param int $quality Compression quality (0-100)
     * @return self
     */
    public function saveToDisk(string $diskName, string $path, ?string $format = null, int $quality = 90): self;

    /**
     * Export image as base64 encoded Data URI string (e.g. data:image/png;base64,....).
     *
     * @param string|null $format
     * @param int $quality
     * @return string
     */
    public function toDataUri(?string $format = null, int $quality = 90): string;

    /**
     * Send direct HTTP headers and stream output to browser.
     *
     * @param string|null $format
     * @param int $quality
     * @return void
     */
    public function response(?string $format = null, int $quality = 90): void;

    /**
     * Export image as a raw byte string (alias of encode).
     *
     * @param string|null $format
     * @param int $quality
     * @return string
     */
    public function toBinary(?string $format = null, int $quality = 90): string;

    /**
     * Export image as a seekable PHP memory stream resource (rewound to beginning).
     * Ideal for low-memory uploading to S3 or custom file writers.
     *
     * @param string|null $format
     * @param int $quality
     * @return resource
     */
    public function toStream(?string $format = null, int $quality = 90);

    /**
     * Pass the current image instance into a given callback or pipeline function.
     *
     * @param callable $callback Receives ($this, ...$args)
     * @param mixed ...$args Additional optional arguments passed to callback
     * @return mixed Result returned by the callback
     */
    public function pipe(callable $callback, ...$args);

    /**
     * Render the image binary and pass ($binaryData, $mimeType, $this) directly to a custom handler.
     *
     * @param callable $callback Receives ($binary, $mime, $this)
     * @param string|null $format
     * @param int $quality
     * @return mixed
     */
    public function export(callable $callback, ?string $format = null, int $quality = 90);

    /**
     * Get the underlying driver image resource or object (\GdImage, resource, or \Imagick).
     *
     * @return mixed
     */
    public function getCore();
}
