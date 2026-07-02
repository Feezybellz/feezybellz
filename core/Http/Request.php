<?php

namespace Framework\Core\Http;

class Request
{
    protected $method;
    protected $uri;
    protected $query;
    protected $post;
    protected $json;
    protected $server;
    protected $headers;
    protected $files;
    protected $params = [];
    
    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $this->parseUri();
        $this->query = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->headers = $this->parseHeaders();
        $this->files = $this->normalizeFiles($_FILES);
        
        // Parse JSON payload ONCE during request initialization
        $jsonData = file_get_contents('php://input');
        $parsedJson = json_decode($jsonData, true);
        $this->json = is_array($parsedJson) ? $parsedJson : [];

        // Method spoofing: only honor `_method` when it came in via a form
        // POST (application/x-www-form-urlencoded or multipart/form-data).
        // Allowing it from JSON bodies meant a CSRF-exempt JSON API could be
        // coerced into DELETE/PUT semantics, escaping any per-verb protections.
        if ($this->method === 'POST') {
            $contentType = strtolower((string) ($this->server['CONTENT_TYPE'] ?? ''));
            $isFormPost  = str_contains($contentType, 'application/x-www-form-urlencoded')
                        || str_contains($contentType, 'multipart/form-data');
            if ($isFormPost && isset($this->post['_method'])) {
                $spoofedMethod = strtoupper((string) $this->post['_method']);
                if (in_array($spoofedMethod, ['PUT', 'PATCH', 'DELETE'], true)) {
                    $this->method = $spoofedMethod;
                }
            }
        }
    }

    public function __get($key) {
        return $this->params[$key] ?? null;
    }
    
    /**
     * Get a route or subdomain parameter (e.g. {id} or {tenant})
     * Industry Standard helper
     */
    public function route(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }
    
    /**
     * Get all route/subdomain parameters
     */
    public function routeParams(): array
    {
        return $this->params;
    }
    
    /**
     * Get the request method
     * 
     * @return string
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Check the request method
     * 
     * @param string $method
     * @return bool
     */
    public function isMethod(string $method): bool
    {
        return strtoupper($this->method) === strtoupper($method);
    }
    
    /**
     * Get the request URI
     * 
     * @return string
     */
    public function uri(): string
    {
        return $this->uri;
    }
    
    /**
     * Get the request host
     * 
     * @return string
     */
    public function host(): string
    {
        return $this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost';
    }

    /**
     * Get the client IP address
     * 
     * @return string
     */
    public function ip(): string
    {
        return $this->server['HTTP_CLIENT_IP'] ?? 
               $this->server['HTTP_X_FORWARDED_FOR'] ?? 
               $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get the current subdomain (e.g., returns 'api' from 'api.myapp.com').
     * Returns null if the request is hitting the apex domain or www host.
     *
     * Resolution:
     *   1. The Host header is stripped of any :port suffix before matching.
     *   2. If APP_DOMAIN is configured, the host must exactly *end* in `.<base>`
     *      to count as a subdomain. This rejects look-alikes like
     *      `evil-example.com` against `example.com`, even though that string
     *      naively "contains" example.com.
     *   3. Without APP_DOMAIN, fall back to "first dot-segment if the host has
     *      3+ parts and isn't a `www.*` host".
     */
    public function subdomain(): ?string
    {
        $host = $this->host();
        if (($colonPos = strpos($host, ':')) !== false) {
            $host = substr($host, 0, $colonPos);
        }

        $appDomains = array_filter(array_map('trim', explode(',', env('APP_DOMAIN', ''))));

        if (!empty($appDomains)) {
            foreach ($appDomains as $base) {
                $base = ltrim($base, '.');
                if ($base === '' || $host === $base || $host === 'www.' . $base) {
                    continue;
                }
                $suffix = '.' . $base;
                if (str_ends_with($host, $suffix)) {
                    // Length-based strip avoids str_replace's all-occurrences risk.
                    return substr($host, 0, -strlen($suffix));
                }
            }
            return null;
        }

        $parts = explode('.', $host);
        if (count($parts) >= 3 && $parts[0] !== 'www') {
            return $parts[0];
        }

        return null;
    }
    
    /**
     * Get a query parameter
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function query($key = null, $default = null)
    {
        if ($key === null) {
            return $this->query;
        }
        
        return $this->query[$key] ?? $default;
    }
    
    /**
     * Get a POST parameter
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function post($key = null, $default = null)
    {
        if ($key === null) {
            return $this->post;
        }
        
        return $this->post[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        $input = $this->all();
        return isset($input[$key]);
    }

    /**
     * Validate the request data against the given rules.
     * 
     * @param array $rules
     * @return array The validated and sanitized data
     * @throws \Framework\Core\Exceptions\ValidationException
     */
    public function validate(array $rules): array
    {
        $validator = \Framework\Core\Validation\Validator::make($this->all(), $rules);

        if ($validator->fails()) {
            throw new \Framework\Core\Exceptions\ValidationException($validator->errors());
        }

        return $validator->validated();
    }

    /**
     * Get all input (query + post + json)
     * * @return array
     */
    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json);
    }
    
    /**
     * Get all input (query + post + json)
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function input($key = null, $default = null)
    {
        $input = $this->all();
        
        if ($key === null) {
            return $input;
        }
        
        return $input[$key] ?? $default;
    }
    public function getIp(){
        $ip = $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
        return $ip;
    }

    public function getBearerToken()
    {

        $authHeader = $this->header('Authorization');
        
        if($authHeader && preg_match('/Bearer\s(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    public function server($key = null, $default = null){
        if ($key === null) {
            return $this->server;
        }
        
        return $this->server[$key] ?? $default;
    }

    /**
     * Get a header
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function header(string $key, $default = null)
    {
        $key = strtoupper($key);
        return $this->headers[$key] ?? $default;
    }
    
    /**
     * Get a header (alias for header method)
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getHeader(string $key, $default = null)
    {
        return $this->header($key, $default);
    }
    
    /**
     * Get the request path (same as uri)
     * 
     * @return string
     */
    public function getPath(): string
    {
        return $this->uri;
    }
    
    /**
     * Parse the request URI
     * 
     * @return string
     */
    protected function parseUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        return $uri;
    }
    
    /**
     * Parse request headers
     * 
     * @return array
     */
    protected function parseHeaders(): array
    {
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            }
        }
        
        return $headers;
    }
    
    /**
     * Check if request is AJAX
     * 
     * @return bool
     */
    public function isAjax(): bool
    {
        return strtolower($this->header('X-REQUESTED-WITH', '')) === 'xmlhttprequest';
    }

    /**
     * Determine if the current request is asking for JSON.
     *
     * @return bool
     */
    public function wantsJson(): bool
    {
        $acceptable = $this->header('Accept', '');
        
        return $this->isAjax() || 
            strpos($acceptable, '/json') !== false || 
            strpos($acceptable, '+json') !== false;
    }

    /**
     * Get a cookie value
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function cookie($key = null, $default = null)
    {
        if ($key === null) {
            return $_COOKIE;
        }
        
        return $_COOKIE[$key] ?? $default;
    }

    /**
     * Set an internal parameter
     */
    public function setParam(string $key, $value): void
    {
        $this->params[$key] = $value;
    }

    /**
     * Get an internal parameter
     */
    public function param(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    public function params($key = null, $default = null)
    {
        if ($key === null) {
            return $this->params;
        }
        
        return $this->params[$key] ?? $default;
    }

    // ==========================================
    // File Management
    // ==========================================

    /**
     * Check if the request has a specific uploaded file
     * 
     * @param string $key
     * @return bool
     */
    public function hasFile(string $key): bool
    {
        $file = $this->files[$key] ?? null;

        if (!$file) {
            return false;
        }

        // Handle array of files
        if (isset($file[0])) {
            return $file[0]['error'] !== UPLOAD_ERR_NO_FILE;
        }

        return $file['error'] !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Get an uploaded file by key
     * 
     * Returns a single UploadedFile or an array of UploadedFile for multiple uploads.
     * Returns null if no file was uploaded for the given key.
     * 
     * @param string $key
     * @return mixed
     */
    public function file(string $key)
    {
        if (!$this->hasFile($key)) {
            return null;
        }

        $file = $this->files[$key];

        // Multiple files
        if (isset($file[0])) {
            return array_map(function($f) { return new UploadedFile($f); }, $file);
        }

        return new UploadedFile($file);
    }

    /**
     * Get all uploaded files
     * 
     * @return array
     */
    public function allFiles(): array
    {
        $result = [];

        foreach ($this->files as $key => $file) {
            if (isset($file[0])) {
                $result[$key] = array_map(function($f) { return new UploadedFile($f); }, $file);
            } else {
                $result[$key] = new UploadedFile($file);
            }
        }

        return $result;
    }

    /**
     * Normalize the $_FILES superglobal into a consistent structure
     * 
     * PHP structures multi-file uploads as separate arrays for each property.
     * This converts them into an array of individual file arrays.
     * 
     * @param array $files
     * @return array
     */
    protected function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $file) {
            // Multiple file upload: $_FILES['photos']['name'][0], etc.
            if (is_array($file['name'])) {
                $normalized[$key] = [];
                $count = count($file['name']);

                for ($i = 0; $i < $count; $i++) {
                    $normalized[$key][] = [
                        'name'     => $file['name'][$i],
                        'type'     => $file['type'][$i],
                        'tmp_name' => $file['tmp_name'][$i],
                        'error'    => $file['error'][$i],
                        'size'     => $file['size'][$i],
                    ];
                }
            } else {
                // Single file upload
                $normalized[$key] = $file;
            }
        }

        return $normalized;
    }
}
