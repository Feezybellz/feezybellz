<?php

namespace Tests\Feature;

use Framework\Core\Testing\HttpTestCase;
use Framework\Core\Http\Response;
use Framework\Core\Routing\Router;

/**
 * Demonstrates the HttpTestCase / TestResponse layer with isolated,
 * test-local routes (overriding routes() switches off the app's real
 * route files).
 */
class HttpExampleTest extends HttpTestCase
{
    protected function routes(): void
    {
        Router::get('/health', fn () => Response::json(['status' => 'up', 'version' => 2]));
        Router::get('/hello', fn () => Response::html('<h1>Hello, world</h1>'));
        Router::get('/go', fn () => Response::redirect('/health'));
        Router::post('/echo', fn () => Response::json(['ok' => true], 201));
    }

    public function test_json_endpoint(): void
    {
        $this->get('/health')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson(['status' => 'up'])
            ->assertJson(['version' => 2]);
    }

    public function test_html_endpoint(): void
    {
        $this->get('/hello')
            ->assertOk()
            ->assertSee('Hello, world')
            ->assertDontSee('Goodbye');
    }

    public function test_redirect(): void
    {
        $this->get('/go')->assertRedirect('/health');
    }

    public function test_post_returns_created(): void
    {
        $this->post('/echo', ['name' => 'Ada'])
            ->assertStatus(201)
            ->assertJson(['ok' => true]);
    }

    public function test_unknown_route_is_not_found(): void
    {
        $this->get('/nope')->assertNotFound();
    }
}
