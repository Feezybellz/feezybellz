<?php

namespace App\Controllers;

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use Framework\Core\Image\Image;
use Exception;

class ImageTesterController
{
    public function index(Request $request): Response
    {
        return Response::view('image_tester');
    }

    public function process(Request $request): Response
    {
        $startTime = microtime(true);
        $input = $request->all();

        try {
            $source = null;

            // 1. Check if user uploaded a main image file
            if (!empty($_FILES['image_file']['tmp_name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $source = $_FILES['image_file'];
            } elseif (!empty($input['image_url'])) {
                $source = trim($input['image_url']);
            }

            // 2. Load or generate canvas
            if ($source) {
                $img = image($source, $input['driver'] ?? 'auto');
            } else {
                // Generate a default vibrant demonstration canvas
                $img = Image::create(800, 500, '#1E293B', $input['driver'] ?? 'auto');
                // Draw decorative design on canvas
                $img->text('Antigravity Image Service', 0, -20, [
                    'size' => 28,
                    'color' => '#38BDF8',
                    'position' => 'center'
                ])->text('800 x 500 Test Canvas', 0, 30, [
                    'size' => 16,
                    'color' => '#94A3B8',
                    'position' => 'center'
                ]);
            }

            // 3. Resizing / Cropping
            if (!empty($input['resize_mode']) && $input['resize_mode'] !== 'none') {
                $w = !empty($input['width']) ? (int) $input['width'] : 400;
                $h = !empty($input['height']) ? (int) $input['height'] : 400;

                if ($input['resize_mode'] === 'resize') {
                    $img->resize($w, !empty($input['height']) ? $h : null);
                } elseif ($input['resize_mode'] === 'cover') {
                    $img->cover($w, $h);
                } elseif ($input['resize_mode'] === 'fit') {
                    $img->fit($w, $h, 'center', '#0F172A');
                }
            }

            // 4. Rotation & Flips
            if (!empty($input['rotate']) && (float) $input['rotate'] != 0) {
                $img->rotate((float) $input['rotate'], '#00000000');
            }
            if (!empty($input['flip']) && $input['flip'] !== 'none') {
                $img->flip($input['flip']);
            }

            // 5. Visual Filters
            if (!empty($input['filter']) && $input['filter'] !== 'none') {
                switch ($input['filter']) {
                    case 'grayscale': $img->grayscale(); break;
                    case 'invert': $img->invert(); break;
                    case 'blur': $img->blur((int) ($input['filter_val'] ?? 2)); break;
                    case 'sharpen': $img->sharpen((int) ($input['filter_val'] ?? 15)); break;
                    case 'brightness': $img->brightness((int) ($input['filter_val'] ?? 20)); break;
                    case 'contrast': $img->contrast((int) ($input['filter_val'] ?? 20)); break;
                    case 'pixelate': $img->pixelate((int) ($input['filter_val'] ?? 10)); break;
                }
            }

            // 6. Logo Watermark
            if (!empty($input['apply_logo_wm']) && $input['apply_logo_wm'] === '1') {
                $logoSource = null;
                if (!empty($_FILES['watermark_file']['tmp_name']) && $_FILES['watermark_file']['error'] === UPLOAD_ERR_OK) {
                    $logoSource = $_FILES['watermark_file'];
                } elseif (!empty($input['watermark_url'])) {
                    $logoSource = trim($input['watermark_url']);
                } else {
                    // Create a simulated brand badge watermark
                    $logo = Image::create(140, 50, '#EC4899EE', $input['driver'] ?? 'auto');
                    $logo->text('★ PROMO', 0, 0, ['size' => 16, 'color' => '#FFFFFF', 'position' => 'center']);
                    $logoSource = $logo;
                }

                $pos = $input['wm_position'] ?? 'bottom-right';
                $opacity = (int) ($input['wm_opacity'] ?? 90);
                $img->watermark($logoSource, $pos, 20, 20, $opacity, 25);
            }

            // 7. Text Watermark / Overlay
            if (!empty($input['watermark_text'])) {
                $img->text(trim($input['watermark_text']), 15, 15, [
                    'size' => (int) ($input['wm_text_size'] ?? 20),
                    'color' => $input['wm_text_color'] ?? '#FFFFFF',
                    'position' => $input['wm_text_pos'] ?? 'top-right',
                    'angle' => (float) ($input['wm_text_angle'] ?? 0),
                ]);
            }

            // 8. Output Encoding & Pipeline verification
            $format = !empty($input['format']) ? strtolower($input['format']) : 'webp';
            $quality = !empty($input['quality']) ? (int) $input['quality'] : 90;

            // Test our new flexible custom pipeline execution
            $pipelineLogs = $img->pipe(function ($instance) use ($format, $quality) {
                $stream = $instance->toStream($format, $quality);
                $meta = stream_get_meta_data($stream);
                $contents = stream_get_contents($stream);
                fclose($stream);

                return [
                    'engine_core_class' => is_object($instance->getCore()) ? get_class($instance->getCore()) : get_resource_type($instance->getCore()),
                    'stream_memory_type' => $meta['stream_type'] ?? 'memory',
                    'stream_temp_uri' => $meta['uri'] ?? 'php://temp',
                    'generated_bytes' => strlen($contents),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            });

            $dataUri = $img->toDataUri($format, $quality);
            $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

            return Response::json([
                'success' => true,
                'data_uri' => $dataUri,
                'width' => $img->getWidth(),
                'height' => $img->getHeight(),
                'mime' => $img->getMime(),
                'format' => strtoupper($format),
                'size_bytes' => $pipelineLogs['generated_bytes'],
                'execution_ms' => $elapsedMs,
                'pipeline' => $pipelineLogs
            ]);
        } catch (Exception $e) {
            return Response::setStatusCode(500)->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
