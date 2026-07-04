<?php

namespace Tests\Feature;

use Framework\Core\Routing\RateLimiter;
use Framework\Core\Testing\TestCase;

/**
 * Converted from test_fixes.php (section 3).
 *
 * Consecutive hits on the same key must increment monotonically, and
 * clear() must reset the counter.
 */
class RateLimiterTest extends TestCase
{
    private string $key = 'unit_test_ip';

    public function test_hits_increment_monotonically(): void
    {
        RateLimiter::clear($this->key);

        $this->assertSame(1, RateLimiter::hit($this->key));
        $this->assertSame(2, RateLimiter::hit($this->key));
        $this->assertSame(3, RateLimiter::hit($this->key));
    }

    public function test_clear_resets_the_counter(): void
    {
        RateLimiter::hit($this->key);
        RateLimiter::clear($this->key);

        $this->assertSame(1, RateLimiter::hit($this->key));
    }

    protected function tearDown(): void
    {
        RateLimiter::clear($this->key);
    }
}
