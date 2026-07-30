<?php

namespace App\Controllers;

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use Exception;

class TesterController
{
    public function index(Request $request): Response
    {
        return Response::view('tester');
    }

    public function handle(Request $request): Response
    {
        $targetUrl = $request->input('proxy_url');
        
        // If no proxy_url is provided, just return the request info as before (for local testing)
        if (!$targetUrl) {
            return $this->echoRequest($request);
        }

        return $this->proxyRequest($request, $targetUrl);
    }

    protected function proxyRequest(Request $request, string $url): Response
    {
        if (!extension_loaded('curl')) {
            return Response::json(['error' => 'CURL extension is not loaded on the server.'], 500);
        }

        $method = strtoupper($request->input('proxy_method', 'GET'));
        $headersInput = $request->input('proxy_headers', []);
        $bodyType = $request->input('proxy_body_type', 'none');
        $bodyContent = $request->input('proxy_body', '');
        $cookiesInput = $request->input('proxy_cookies', '');
        
        $ch = curl_init();
        
        // Set URL and Method
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // Include headers in output
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // Process Headers
        $curlHeaders = [];
        if (is_array($headersInput)) {
            foreach ($headersInput as $key => $val) {
                if (!empty($key)) $curlHeaders[] = "$key: $val";
            }
        }

        if (!empty($cookiesInput)) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookiesInput);
        }

        // Handle Body and Files
        if ($method !== 'GET') {
            if ($bodyType === 'json') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyContent);
                $curlHeaders[] = 'Content-Type: application/json';
            } elseif ($bodyType === 'form-data') {
                $postData = [];
                // Parse key=value pairs
                foreach (explode("\n", $bodyContent) as $line) {
                    $parts = explode('=', $line, 2);
                    if (count($parts) === 2) {
                        $postData[trim($parts[0])] = trim($parts[1]);
                    }
                }
                
                // Add File
                $fileFieldName = $request->input('proxy_file_field', 'file');
                if ($request->hasFile('file')) {
                    $file = $request->file('file');
                    // PHP 5.5+ uses CURLFile
                    $postData[$fileFieldName] = new \CURLFile(
                        $file->getTempPath(), 
                        $file->getClientMimeType(), 
                        $file->getClientOriginalName()
                    );
                }
                
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                // CURL sets content-type for multipart automatically
            } elseif ($bodyType === 'x-www-form-urlencoded') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyContent);
                $curlHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
            } elseif ($bodyType === 'binary') {
                if ($request->hasFile('file')) {
                    $file = $request->file('file');
                    $fileData = file_get_contents($file->getTempPath());
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
                    $curlHeaders[] = 'Content-Type: ' . ($file->getClientMimeType() ?: 'application/octet-stream');
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyContent);
                }
            } elseif ($bodyType === 'raw') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyContent);
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

        // Execute
        $startTime = microtime(true);
        $response = curl_exec($ch);
        $endTime = microtime(true);
        
        if (curl_errno($ch)) {
            return Response::json([
                'error' => 'CURL Error: ' . curl_error($ch)
            ], 500);
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        
        $responseHeadersRaw = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);
        
        curl_close($ch);

        // Parse Response Headers
        $parsedHeaders = [];
        foreach (explode("\n", $responseHeadersRaw) as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $parsedHeaders[trim($parts[0])] = trim($parts[1]);
            }
        }

        // Calculate size
        $size = strlen($responseBody);

        return Response::json([
            'status' => $httpCode,
            'time_ms' => round(($endTime - $startTime) * 1000),
            'size' => $size,
            'size_readable' => $this->formatBytes($size),
            'headers' => $parsedHeaders,
            'body' => $responseBody,
            'content_type' => $contentType,
            'is_json' => (strpos($contentType, 'json') !== false)
        ]);
    }

    protected function echoRequest(Request $request): Response
    {
        $allHeaders = function_exists('getallheaders') ? getallheaders() : $this->getSimulatedHeaders();
        $filesData = [];
        foreach ($request->allFiles() as $key => $file) {
            if (is_array($file)) {
                foreach ($file as $f) $filesData[$key][] = $this->processFile($f, $request);
            } else {
                $filesData[$key] = $this->processFile($file, $request);
            }
        }

        $rawBody = file_get_contents('php://input');
        return Response::json([
            'info' => 'Echoing your request (No proxy URL provided)',
            'method' => $request->method(),
            'headers' => $allHeaders,
            'files' => $filesData,
            'raw_body' => [
                'size' => strlen($rawBody),
                'preview' => $this->isBinary($rawBody) ? 'Binary Data' : substr($rawBody, 0, 500)
            ]
        ]);
    }

    protected function processFile($file, Request $request)
    {
        if (!$file->isValid()) return ['error' => $file->getErrorMessage()];
        $data = [
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size' => $file->getSize(),
            'readable_size' => $file->getReadableSize(),
        ];
        if ($request->input('convert_base64') === 'true' || $request->input('convert_base64') === '1') {
            $data['base64'] = 'data:' . $data['mime'] . ';base64,' . base64_encode(file_get_contents($file->getTempPath()));
        }
        return $data;
    }

    protected function getSimulatedHeaders()
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $bytes;
        for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, $precision) . ' ' . $units[$i];
    }

    protected function isBinary($data)
    {
        return !empty($data) && preg_match('~[^\x20-\x7E\t\r\n]~', $data) > 0;
    }
}
