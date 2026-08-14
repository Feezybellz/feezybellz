<?php

namespace App\Controllers;

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;

class ToolsController
{
    public function index(Request $request): Response
    {
        $tools = [
            [
                'name' => 'AI Metadata Remover',
                'slug' => 'remove-ai-metadata',
                'description' => 'Strip EXIF, IPTC, and AI generation metadata from multiple images at once.',
                'category' => 'Image Processing',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>'
            ],
            [
                'name' => 'Gemini Watermark Remover',
                'slug' => 'remove-gemini-watermark',
                'description' => 'Automatically crop out the subtle gem watermark from Gemini / Imagen generated images.',
                'category' => 'Image Processing',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>'
            ],
            [
                'name' => 'Pro QR Code Generator',
                'slug' => 'qr-generator',
                'description' => 'Generate highly customizable QR codes with custom colors, shapes, logos, and support for WiFi, vCard, and more.',
                'category' => 'Utilities',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>'
            ],
            [
                'name' => 'PDF Signature Editor',
                'slug' => 'pdf-editor',
                'description' => 'A powerful browser-based PDF editor. Sign documents securely, add text, and save your signature locally.',
                'category' => 'Document Processing',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>',
                'tags' => ['pdf', 'editor', 'sign', 'document', 'text', 'format']
            ],
            [
                'name' => 'IT Tools',
                'slug' => 'https://it-tools.tech/',
                'description' => 'Collection of handy online tools for developers, with great UX. Free and open-source utilities.',
                'category' => 'External Tools',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg>',
                'is_external' => true,
                'tags' => ['developer', 'utilities', 'crypto', 'converters', 'generators', 'external']
            ],
            [
                'name' => 'NoSignups',
                'slug' => 'https://nosignups.net/',
                'description' => 'A directory of no-signup, in-browser, open-source tools you can use instantly in your browser.',
                'category' => 'External Tools',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>',
                'is_external' => true,
                'tags' => ['directory', 'no-signup', 'open-source', 'browser', 'privacy', 'external']
            ]
        ];

        // Add default tags and is_external to the first 3 tools just in case
        $tools[0]['tags'] = ['image', 'metadata', 'exif', 'privacy', 'ai', 'clean'];
        $tools[0]['is_external'] = false;
        $tools[1]['tags'] = ['image', 'watermark', 'gemini', 'imagen', 'crop'];
        $tools[1]['is_external'] = false;
        $tools[2]['tags'] = ['qr', 'generator', 'code', 'vcard', 'wifi'];
        $tools[2]['is_external'] = false;
        $tools[3]['is_external'] = false;

        return Response::view('tools/index', ['tools' => $tools]);
    }

    public function show(Request $request): Response
    {
        $tool = $request->route('tool');
        
        // Sanitize tool name to prevent directory traversal
        if (!preg_match('/^[a-zA-Z0-9\-]+$/', $tool)) {
            return Response::setStatusCode(404)->view('errors/404');
        }
        
        try {
            return Response::view("tools/{$tool}");
        } catch (\Throwable $e) {
            return Response::setStatusCode(404)->view('errors/404');
        }
    }

    public function removeMetadata(Request $request): Response
    {
        try {
            $source = null;
            if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $source = $_FILES['image'];
            }

            if (!$source) {
                return Response::json(['success' => false, 'error' => 'No image uploaded']);
            }

            $img = image($source);
            
            // Check if we need to crop the bottom (for watermark removal)
            $input = $request->all();
            if (!empty($input['crop_bottom']) && is_numeric($input['crop_bottom'])) {
                $cropAmount = (int) $input['crop_bottom'];
                $newHeight = $img->getHeight() - $cropAmount;
                if ($newHeight > 0) {
                    // Crop from the top-left (0,0) down to newHeight
                    $img->crop($img->getWidth(), $newHeight, 0, 0);
                }
            }
            
            $originalName = $_FILES['image']['name'];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $extension = 'jpeg';
            }
            if ($extension === 'jpg') {
                $extension = 'jpeg';
            }

            $dataUri = $img->toDataUri($extension, 100);

            return Response::json([
                'success' => true,
                'data_uri' => $dataUri,
                'filename' => 'cleaned_' . $originalName
            ]);

        } catch (\Throwable $e) {
            return Response::setStatusCode(500)->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
