<?php

namespace Framework\Core\Http;

/**
 * Fluent Response Facade
 * Supports: 
 * - Response::json()
 * - Response::setStatusCode(201)->json()
 * - (new Response())->setStatusCode(200)->json()
 */
class Response
{
    protected $statusCode = 200;
    protected $headers = [];
    protected $content = '';

    /** @var self|null Singleton instance for static calls */
    protected static $instance = null;

    /**
     * Get the global singleton instance
     */
    protected static function getInstance(): self
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Captures static calls like Response::json()
     */
    public static function __callStatic($method, $args)
    {
        return static::getInstance()->$method(...$args);
    }

    /**
     * Captures instance calls like $response->json()
     */
    public function __call($method, $args)
    {
        $internalMethod = '_' . $method;
        if (method_exists($this, $internalMethod)) {
            return $this->$internalMethod(...$args);
        }
        
        throw new \BadMethodCallException("Method {$method} does not exist.");
    }

    // =========================================================
    // INTERNAL LOGIC
    // =========================================================

    protected function _setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    protected function _getStatusCode(): int
    {
        return $this->statusCode;
    }

    protected function _getContent(): string {
        return $this->content;
    }

    protected function _getBody(): string
    {
        return $this->_getContent();
    }

    protected function _getHeaders(): array
    {
        return $this->headers;
    }

    protected function _getHeader(string $key)
    {
        return $this->headers[$key] ?? null;
    }

    protected function _setHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function header(string $key, string $value): self
    {
        return $this->_setHeader($key, $value);
    }

    protected function _setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    protected function _json($data, int $statusCode = null): self
    {
        if ($statusCode) $this->_setStatusCode($statusCode);
        $this->_setHeader('Content-Type', 'application/json');

        if (function_exists('recursive_format_dates')) {
            $data = recursive_format_dates($data);
        }

        $this->content = json_encode($data);
        return $this;
    }

    protected function _html(string $content, int $statusCode = null): self
    {
        if ($statusCode) $this->_setStatusCode($statusCode);
        $this->_setHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->content = $content;
        return $this;
    }

    protected function _view(string $name, array $data = [], int $statusCode = null): self
    {
        return $this->_html(view($name, $data), $statusCode);
    }

    protected function _redirect(string $url, int $statusCode = 302): self
    {
        $this->_setStatusCode($statusCode);
        $this->_setHeader('Location', $url);
        return $this;
    }

    /**
     * High-performance file streaming with browser caching
     */
    protected function _file(string $path, string $contentType = 'application/javascript'): void
    {
        if (!file_exists($path)) {
            http_response_code(404);
            echo 'File not found.';
            exit;
        }

        $mtime = filemtime($path);
        $etag = md5($path . $mtime);

        if ((isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)) {
            http_response_code(304);
            exit;
        }

        header("Content-Type: {$contentType}");
        header("Cache-Control: public, max-age=31536000");
        header("ETag: {$etag}");
        header("Content-Length: " . filesize($path));

        readfile($path);
        exit;
    }

    // =========================================================
    // EXECUTION
    // =========================================================

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $key => $value) {
                header("{$key}: {$value}");
            }
        }
        echo $this->content;
    }
}
