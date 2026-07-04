<?php

namespace Framework\Core\Testing;

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use Framework\Core\Routing\Router;

/**
 * Base class for HTTP / feature tests.
 *
 * Drives the framework's router in-process (no web server, no sockets)
 * and returns a {@see TestResponse} for fluent assertions:
 *
 *   class HealthTest extends HttpTestCase
 *   {
 *       protected function routes(): void
 *       {
 *           Router::get('/health', fn () => Response::json(['status' => 'up']));
 *       }
 *
 *       public function test_health_endpoint(): void
 *       {
 *           $this->get('/health')->assertOk()->assertJson(['status' => 'up']);
 *       }
 *   }
 *
 * By default the application's real route files are loaded, so you can
 * test the routes you actually ship. Override {@see routes()} to register
 * routes inline instead — doing so switches off loading the app's files
 * so the test stays isolated.
 */
abstract class HttpTestCase extends TestCase
{
    /**
     * Register test-local routes. If you override this, the application's
     * route files are NOT auto-loaded (the test is self-contained).
     */
    protected function routes(): void
    {
    }

    protected function setUp(): void
    {
        parent::setUp();
        Router::clearRoutes();
    }

    protected function tearDown(): void
    {
        // Reset superglobals mutated during the request simulation.
        $_POST = [];
        $_GET = [];
        unset($_SERVER['CONTENT_TYPE']);
        parent::tearDown();
    }

    // ── Verbs ───────────────────────────────────────────────────────────

    protected function get(string $uri, array $headers = []): TestResponse
    {
        return $this->call('GET', $uri, [], $headers);
    }

    protected function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, $data, $headers);
    }

    protected function put(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('PUT', $uri, $data, $headers);
    }

    protected function patch(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('PATCH', $uri, $data, $headers);
    }

    protected function delete(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('DELETE', $uri, $data, $headers);
    }

    protected function postJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->post($uri, $data, ['Content-Type' => 'application/json'] + $headers);
    }

    // ── Core dispatch ───────────────────────────────────────────────────

    /**
     * Simulate a request through the router and wrap the result.
     */
    protected function call(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $parts = explode('?', $uri, 2);
        $path = $parts[0];

        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['REQUEST_URI'] = $uri;
        // The router locks global routes to the configured APP_DOMAIN apex,
        // so default the host to it (a "Host" header below can override).
        $_SERVER['HTTP_HOST'] = $this->defaultHost();

        // Parse a query string, if present, into $_GET.
        $_GET = [];
        if (isset($parts[1])) {
            parse_str($parts[1], $_GET);
        }

        // Body data lands in $_POST for non-GET verbs (Request::all() reads it).
        $_POST = ($method === 'GET') ? [] : $data;

        // Map friendly header names onto the CGI-style $_SERVER keys the
        // Request object reads (e.g. "Content-Type" -> HTTP_CONTENT_TYPE,
        // and CONTENT_TYPE which the WAF/Request also consult).
        foreach ($headers as $key => $value) {
            $server = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
            $_SERVER[$server] = $value;
            if (strtolower($key) === 'content-type') {
                $_SERVER['CONTENT_TYPE'] = $value;
            }
        }

        Router::init(new Request(), new Response());

        // Register routes: test-local if routes() is overridden, otherwise
        // the application's real route files.
        if ($this->hasCustomRoutes()) {
            $this->routes();
        } else {
            Router::loadRoutesFrom($this->app->basePath('routes'));
        }

        return new TestResponse(Router::dispatch(), $this);
    }

    /**
     * The host to simulate: the apex of the configured APP_DOMAIN when set
     * (so global routes match), otherwise "localhost".
     */
    private function defaultHost(): string
    {
        $domain = function_exists('config') ? (string) config('app.domain', '') : '';
        if ($domain === '') {
            return 'localhost';
        }
        return trim(explode(',', $domain)[0]);
    }

    /**
     * True when the concrete test class overrides routes() — meaning it
     * wants isolated, test-local routing.
     */
    private function hasCustomRoutes(): bool
    {
        $method = new \ReflectionMethod($this, 'routes');
        return $method->getDeclaringClass()->getName() !== self::class;
    }
}
