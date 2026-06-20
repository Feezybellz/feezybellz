<?php

namespace Framework\Core\Exceptions;

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use Framework\Core\Logging\Log;

class Handler
{
    protected $debug;

    public function __construct()
    {
        // Check if the app is in debug mode (from .env)
        $this->debug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Register the global error and exception hooks
     */
    public function register(): void
    {
        // Catch all uncaught exceptions
        set_exception_handler([$this, 'handleException']);

        // Catch native PHP errors (warnings, notices) and convert them to Exceptions
        set_error_handler(function ($level, $message, $file = '', $line = 0) {
            if (error_reporting() & $level) {
                throw new \ErrorException($message, 0, $level, $file, $line);
            }
        });

        // Catch fatal errors (like memory exhaustion or syntax errors)
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
                $this->handleException(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
            }
        });
    }

    /**
     * Handle the exception and render the appropriate response
     */
    public function handleException(\Throwable $e): void
    {
        // Log the error using the Log Facade
        Log::error(get_class($e) . ': ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        // Check if running in CLI
        if (php_sapi_name() === 'cli' || defined('STDIN')) {
            $this->renderCli($e);
            return;
        }

        $request = new Request();
        $response = new Response();

        $statusCode = $this->getStatusCode($e);
        $response->setStatusCode($statusCode);

        // If it's an API request (JSON expected) or forced via .env, return JSON
        if ($this->shouldReturnJson($request, $e)) {
            if ($e instanceof ValidationException) {
                $response->setStatusCode(422);
                $response->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->getErrors()
                ]);
                $response->send();
                exit;
            }
            $this->renderJson($e, $response, $statusCode);
            return;
        }

        // Handle Form Validation failures for Web Routes
        if ($e instanceof ValidationException) {
            $referer = $_SERVER['HTTP_REFERER'] ?? '/';
            // Assuming we have Session available via Container or static access
            if (class_exists('\Framework\Core\Http\Session')) {
                \Framework\Core\Http\Session::flash('errors', $e->getErrors());
                \Framework\Core\Http\Session::flash('old', $request->all());
            }
            $response->setStatusCode(302);
            $response->setHeader('Location', $referer);
            $response->send();
            exit;
        }

        // Otherwise, render an HTML error page
        $this->renderHtml($e, $response, $statusCode);
    }

    /**
     * Determine if the exception should be rendered as JSON.
     */
    protected function shouldReturnJson(Request $request, \Throwable $e): bool
    {
        // 1. Check if the developer explicitly forced JSON globally via .env
        if (filter_var(env('APP_FORCE_JSON', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        // 2. Check if the client explicitly requested JSON via headers
        if ($request->wantsJson()) {
            return true;
        }

        // 3. Check if the request is hitting an API route
        if (strpos($request->uri(), '/api/') === 0) {
            return true;
        }

        return false;
    }

    protected function renderCli(\Throwable $e): void
    {
        $title = get_class($e);
        $message = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();

        echo "\n\033[31;1m[ERROR] {$title}\033[0m\n";
        echo "Message: {$message}\n";
        echo "File: {$file}\n";
        echo "Line: {$line}\n";

        if ($this->debug) {
            echo "\nStack Trace:\n";
            echo $e->getTraceAsString();
            echo "\n";
        }
        exit(1);
    }

    protected function renderJson(\Throwable $e, Response $response, int $statusCode): void
    {
        $payload = [
            'error' => true,
            'message' => $this->debug ? $e->getMessage() : 'Server Error',
        ];

        if ($this->debug) {
            $payload['exception'] = get_class($e);
            $payload['file'] = $e->getFile();
            $payload['line'] = $e->getLine();
            $payload['trace'] = $e->getTrace();
        }

        $response->json($payload);
        $response->send();
        exit;
    }

    protected function renderHtml(\Throwable $e, Response $response, int $statusCode): void
    {
        // If debug is off, show a generic, safe error message to users
        $title = $this->debug ? get_class($e) : "Whoops, looks like something went wrong.";
        $message = $this->debug ? $e->getMessage() : "We are experiencing technical difficulties. Please try again later.";
        
        $response->setStatusCode($statusCode);
        
        // Output inline HTML to guarantee it renders even if the View engine fails
        $html = "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <title>Error {$statusCode}</title>
            <style>
                body { font-family: system-ui, sans-serif; background: #f3f4f6; color: #1f2937; padding: 3rem; }
                .container { max-width: 800px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
                h1 { color: #dc2626; margin-top: 0; }
                .trace { background: #1f2937; color: #d1d5db; padding: 1rem; border-radius: 4px; overflow-x: auto; font-family: monospace; font-size: 0.875rem; }
                .file { font-weight: bold; color: #374151; margin-top: 1rem; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>{$statusCode} | {$title}</h1>
                <p>{$message}</p>";

        if ($this->debug) {
            $html .= "<div class='file'>Thrown in <code>{$e->getFile()}</code> on line <strong>{$e->getLine()}</strong></div>";
            $html .= "<h3>Stack Trace:</h3>";
            $html .= "<pre class='trace'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        }

        $html .= "</div></body></html>";
        
        $response->setContent($html);
        $response->send();
        exit;
    }

    protected function getStatusCode(\Throwable $e): int
    {
        // If we created custom exceptions (like HttpException), we could pull the exact code
        // Default to 500 Internal Server Error
        return 500;
    }
}
