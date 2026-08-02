<?php

namespace Tests\Unit;

use Framework\Core\Testing\TestCase;
use Framework\Core\Image\Image;
use Framework\Core\Image\Drivers\ImageDriverInterface;
use Framework\Core\Storage\Storage;
use Framework\Core\Image\Exceptions\ImageException;

class ImageTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Image::useDriver('auto');
        if (file_exists(__DIR__ . '/test_output.jpg')) {
            @unlink(__DIR__ . '/test_output.jpg');
        }
        if (file_exists(__DIR__ . '/test_logo.png')) {
            @unlink(__DIR__ . '/test_logo.png');
        }
    }

    public function test_can_create_blank_canvas_and_query_dimensions(): void
    {
        $img = Image::create(300, 200, '#FF0000', 'gd');
        $this->assertInstanceOf(ImageDriverInterface::class, $img);
        $this->assertEquals(300, $img->getWidth());
        $this->assertEquals(200, $img->getHeight());
        $this->assertEquals('image/png', $img->getMime());
    }

    public function test_can_resize_and_crop_image(): void
    {
        $img = Image::create(400, 400, '#00FF00', 'gd');
        
        // Resize while preserving aspect ratio
        $img->resize(200);
        $this->assertEquals(200, $img->getWidth());
        $this->assertEquals(200, $img->getHeight());

        // Crop center
        $img->crop(100, 50);
        $this->assertEquals(100, $img->getWidth());
        $this->assertEquals(50, $img->getHeight());
    }

    public function test_can_cover_and_thumbnail_image(): void
    {
        $img = Image::create(600, 400, '#0000FF', 'gd');
        $img->cover(150, 150);
        $this->assertEquals(150, $img->getWidth());
        $this->assertEquals(150, $img->getHeight());

        $thumb = Image::create(500, 300, '#FFFF00', 'gd')->thumbnail(80);
        $this->assertEquals(80, $thumb->getWidth());
        $this->assertEquals(80, $thumb->getHeight());
    }

    public function test_can_add_text_and_logo_watermark(): void
    {
        // Create a fake logo to watermark with
        $logo = Image::create(50, 50, '#FF00FF88', 'gd');
        $logoPath = __DIR__ . '/test_logo.png';
        $logo->save($logoPath);

        $main = Image::create(400, 300, '#CCCCCC', 'gd');
        
        // Apply logo watermark
        $main->watermark($logoPath, 'bottom-right', 15, 15, 80, 20);

        // Apply text watermark
        $main->text('© 2026 Framework', 10, 10, [
            'size' => 20,
            'color' => '#000000',
            'position' => 'top-left'
        ]);

        $this->assertEquals(400, $main->getWidth());
        $this->assertEquals(300, $main->getHeight());
        $this->assertNotEmpty($main->encode('png'));
    }

    public function test_can_load_from_simulated_files_array(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img_test_');
        $sample = Image::create(120, 120, '#112233', 'gd')->encode('png');
        file_put_contents($tmp, $sample);

        $filesArray = [
            'name' => 'upload.png',
            'type' => 'image/png',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($sample)
        ];

        $img = image($filesArray);
        $this->assertEquals(120, $img->getWidth());
        $this->assertEquals(120, $img->getHeight());

        @unlink($tmp);
    }

    public function test_can_save_to_local_file_and_storage_disk(): void
    {
        $img = Image::create(200, 200, '#AABBCC', 'gd');

        // Local save
        $outPath = __DIR__ . '/test_output.jpg';
        $img->save($outPath, 'jpg', 85);
        $this->assertTrue(file_exists($outPath));

        // Storage disk save
        Storage::reset();
        Storage::disk('local')->put('test_init.txt', 'init');
        
        $img->saveToDisk('local', 'avatars/test_avatar.webp', 'webp', 90);
        $this->assertTrue(Storage::disk('local')->exists('avatars/test_avatar.webp'));
        
        // Load from disk
        $loaded = Image::fromDisk('local', 'avatars/test_avatar.webp', 'gd');
        $this->assertEquals(200, $loaded->getWidth());

        // Cleanup storage
        Storage::disk('local')->delete('avatars/test_avatar.webp');
        Storage::disk('local')->delete('test_init.txt');
    }

    public function test_data_uri_output(): void
    {
        $img = Image::create(10, 10, '#000000', 'gd');
        $dataUri = $img->toDataUri('png');
        $this->assertTrue(str_starts_with($dataUri, 'data:image/png;base64,'));
    }

    public function test_imagick_driver_if_available(): void
    {
        if (!extension_loaded('imagick') || !class_exists('Imagick')) {
            $this->markTestSkipped('Imagick extension is not installed.');
        }

        $img = Image::create(300, 300, '#FFAA00', 'imagick');
        $this->assertEquals(300, $img->getWidth());

        $img->resize(150)->grayscale()->blur(2);
        $this->assertEquals(150, $img->getWidth());
        $this->assertNotEmpty($img->encode('jpg'));
    }

    public function test_flexible_extraction_and_custom_pipelines(): void
    {
        $img = Image::create(50, 50, '#334455', 'gd');

        // Test toBinary
        $binary = $img->toBinary('png');
        $this->assertNotEmpty($binary);

        // Test toStream
        $stream = $img->toStream('png');
        $this->assertTrue(is_resource($stream));
        $contentFromStream = stream_get_contents($stream);
        $this->assertEquals($binary, $contentFromStream);
        fclose($stream);

        // Test pipe
        $result = $img->pipe(function ($instance, $multiplier) {
            return $instance->getWidth() * $multiplier;
        }, 3);
        $this->assertEquals(150, $result);

        // Test export
        $exported = $img->export(function ($bin, $mime, $instance) {
            return [
                'length' => strlen($bin),
                'mime'   => $mime,
                'w'      => $instance->getWidth()
            ];
        }, 'webp');
        $this->assertEquals('image/webp', $exported['mime']);
        $this->assertNotEmpty($exported['length']);
        $this->assertEquals(50, $exported['w']);
    }

    public function test_watermark_from_uploaded_files_array(): void
    {
        $main = Image::create(400, 400, '#222222', 'gd');
        $logo = Image::create(100, 50, '#FF0000', 'gd');
        
        $tmpFile = tempnam(sys_get_temp_dir(), 'wm_test_');
        file_put_contents($tmpFile, $logo->encode('png'));

        $simulatedFilesUpload = [
            'name' => 'logo.png',
            'type' => 'image/png',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile)
        ];

        // This previously threw "Cannot load image from source [empty string]" because is_string rejected the array
        $main->watermark($simulatedFilesUpload, 'bottom-right', 10, 10, 80, 20);

        $this->assertEquals(400, $main->getWidth());
        $this->assertNotEmpty($main->encode('jpg'));

        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
    }
}
