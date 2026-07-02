<?php

namespace Framework\Core\Http;

/**
 * Fluent Response Facade
 *
 * Supports both styles:
 *   Response::json($data)                      // static entry point
 *   Response::setStatusCode(201)->json($data)  // chained
 *   (new Response())->json($data)              // explicit instance
 *
 * IMPORTANT — no shared singleton.
 *
 * Every static call creates a fresh Response instance internally. That means:
 *
 *   $a = Response::setStatusCode(201);      // instance A, status = 201
 *   $b = Response::json(['ok' => true]);    // instance B, status = 200 (default)
 *
 * The old design used a process-wide singleton, so those two calls would have
 * shared state — instance B's status would silently inherit A's 201. That was
 * fine in FPM (fresh process per request) but leaked between requests in queue
 * workers, WebSocket servers, and any long-running SAPI. It also made it
 * possible for exception-handler code to inherit stale headers set by a
 * controller earlier in the request. Fresh-per-call kills both problems.
 *
 * Chaining still works because the first static call *returns* the fresh
 * instance and every subsequent method in the chain is an instance method
 * operating on that same instance.
 */
class Response
{
    protected $statusCode = 200;
    protected $headers = [];
    protected $content = '';

    /**
     * Every static entry point creates a NEW instance and forwards to it.
     * See the class docblock for the rationale.
     */
    public static function __callStatic($method, $args)
    {
        return (new static())->$method(...$args);
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

        // Date-format transform is opt-out. It walks the entire response
        // tree, which is expensive on large payloads. Set
        // config('app.format_json_dates') = false (or env
        // JSON_FORMAT_DATES=false) to skip it — Models can pre-format their
        // own date fields via casts / accessors, which is cheaper.
        $shouldFormat = true;
        if (function_exists('config')) {
            $cfg = config('app.format_json_dates');
            if ($cfg !== null) $shouldFormat = (bool) $cfg;
        }
        if ($shouldFormat && function_exists('recursive_format_dates')) {
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
        // Clear any body content — some proxies and intermediaries misbehave
        // on 3xx responses with a body. Also drop Content-Type since there
        // is no body left to describe.
        $this->content = '';
        unset($this->headers['Content-Type']);
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
