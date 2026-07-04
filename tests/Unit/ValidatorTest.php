<?php

namespace Tests\Unit;

use Framework\Core\Validation\Validator;
use Framework\Core\Testing\TestCase;

/**
 * Converted from the ad-hoc Validator verifier (claude_fix.md).
 */
class ValidatorTest extends TestCase
{
    // Validator is self-contained — no framework bootstrap needed.
    protected function setUp(): void
    {
    }

    public function test_passing_ruleset(): void
    {
        $v = Validator::make(
            ['name' => 'Ada', 'email' => 'ada@example.com', 'age' => '30'],
            ['name' => 'required|string', 'email' => 'required|email', 'age' => 'integer']
        );

        $this->assertTrue($v->passes());
        $this->assertFalse($v->fails());
        $this->assertEmpty($v->errors());
    }

    public function test_failing_ruleset_reports_errors(): void
    {
        $v = Validator::make(
            ['name' => '', 'email' => 'not-an-email'],
            ['name' => 'required', 'email' => 'required|email']
        );

        $this->assertTrue($v->fails());
        $this->assertTrue($v->hasError('name'));
        $this->assertTrue($v->hasError('email'));
        $this->assertNotNull($v->firstError('email'));
    }

    public function test_validated_returns_only_validated_data(): void
    {
        $v = Validator::make(
            ['keep' => 'yes', 'ignored' => 'x'],
            ['keep' => 'required|string']
        );

        $this->assertTrue($v->passes());
        $this->assertArrayHasKey('keep', $v->validated());
        $this->assertArrayNotHasKey('ignored', $v->validated());
    }
}
