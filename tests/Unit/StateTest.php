<?php

namespace Tests\Unit;

use Framework\Core\State;
use Framework\Core\Routing\Router;
use Framework\Core\Testing\TestCase;

/**
 * Converted from the ad-hoc State verifier (claude_fix.md).
 *
 * State::resetPerRequest() is what keeps a long-lived worker (Swoole /
 * RoadRunner) from leaking per-request static state between requests.
 */
class StateTest extends TestCase
{
    public function test_reset_per_request_clears_registered_routes(): void
    {
        Router::clearRoutes();
        Router::get('/leak-check', fn () => 'x');
        $this->assertCount(1, Router::getRoutes());

        State::resetPerRequest();

        $this->assertCount(0, Router::getRoutes());
    }

    public function test_reset_per_request_flushes_event_listeners(): void
    {
        \Framework\Core\Events\Dispatcher::listen(\stdClass::class, fn () => null);

        State::resetPerRequest();

        $ref = new \ReflectionClass(\Framework\Core\Events\Dispatcher::class);
        $prop = $ref->getProperty('listeners');
        $prop->setAccessible(true);
        $this->assertEmpty($prop->getValue());
    }

    public function test_reset_all_runs_without_error(): void
    {
        // Full teardown must be safe to call even with nothing registered.
        State::resetAll();
        $this->assertTrue(true);
    }
}
