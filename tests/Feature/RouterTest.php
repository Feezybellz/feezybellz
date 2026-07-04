<?php

namespace Tests\Feature;

use Framework\Core\Routing\Router;
use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use Framework\Core\Testing\TestCase;

/**
 * Converted from test_fixes.php (section 1).
 *
 * Exercises route compilation and dispatch of a parameterised route.
 * The base TestCase::setUp() boots the app and binds the Router container.
 */
class RouterTest extends TestCase
{
    public function test_route_pattern_is_compiled(): void
    {
        $this->registerRoutes('/api/users/1');

        $routes = Router::getRoutes();
        $this->assertNotEmpty($routes);
        $this->assertNotEmpty($routes[0]->compiledPattern);
    }

    public function test_parameterised_route_dispatches(): void
    {
        $this->registerRoutes('/api/users/123');

        $response = Router::dispatch();
        $this->assertSame('User: 123', $response->getContent());
    }

    /** Rebuild the request/router state for a given target URI. */
    private function registerRoutes(string $uri): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_HOST'] = 'framework.net.ng';

        Router::init(new Request(), new Response());
        Router::clearRoutes();

        Router::get('/api/users/{id}', fn ($id) => "User: {$id}");
        Router::get('/api/posts', fn () => 'Posts');
    }
}
