<?php

namespace Framework\Core\Testing;

use Framework\Core\Application;
use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use Framework\Core\Routing\Router;

abstract class TestCase
{
    protected $app;
    protected $response;

    /**
     * Setup the test environment before each test method.
     */
    protected function setUp(): void
    {
        // Initialize the app using the project root
        $this->app = new Application(dirname(__DIR__, 2));
        Router::setContainer($this->app);
    }

    /**
     * Simulate a GET request to the framework.
     */
    protected function get(string $uri, array $headers = []): Response
    {
        return $this->call('GET', $uri, [], $headers);
    }

    /**
     * Simulate a POST request.
     */
    protected function post(string $uri, array $data = [], array $headers = []): Response
    {
        return $this->call('POST', $uri, $data, $headers);
    }

    /**
     * Core method to simulate an HTTP call without a browser.
     */
    protected function call(string $method, string $uri, array $data = [], array $headers = []): Response
    {
        // Mock the server environment
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        
        $request = new Request(); // Request will now parse the mocked $_SERVER
        $this->response = new Response();

        Router::init($request, $this->response);
        Router::loadRoutesFrom($this->app->basePath('routes'));

        return Router::dispatch();
    }

    /**
     * Custom assertion helper
     */
    protected function assertEquals($expected, $actual, $message = ''): void
    {
        if ($expected !== $actual) {
            echo "❌ FAIL: {$message}\n   Expected: " . json_encode($expected) . "\n   Actual:   " . json_encode($actual) . "\n";
        } else {
            echo "✅ PASS: {$message}\n";
        }
    }
}
