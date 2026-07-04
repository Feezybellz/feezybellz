<?php

namespace Tests\Unit;

use Framework\Core\Testing\TestCase;

/**
 * A starter test demonstrating the framework's built-in test runner.
 *
 * Run with:  php console test
 */
class ExampleTest extends TestCase
{
    public function test_basic_assertions_pass(): void
    {
        $this->assertTrue(1 + 1 === 2);
        $this->assertSame('framework', 'frame' . 'work');
        $this->assertEquals(10, 5 * 2);
        $this->assertCount(3, ['a', 'b', 'c']);
        $this->assertContains('b', ['a', 'b', 'c']);
        $this->assertArrayHasKey('name', ['name' => 'Ada']);
        $this->assertMatchesRegExp('/^v\d+$/', 'v42');
    }

    public function test_expected_exceptions_are_caught(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        throw new \RuntimeException('boom goes the dynamite');
    }

    public function test_null_and_empty_helpers(): void
    {
        $this->assertNull(null);
        $this->assertNotNull('x');
        $this->assertEmpty([]);
        $this->assertNotEmpty(['x']);
    }
}
