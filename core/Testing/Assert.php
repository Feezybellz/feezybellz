<?php

namespace Framework\Core\Testing;

/**
 * The framework's assertion library — a zero-dependency, PHPUnit-flavoured
 * set of throwing assertions.
 *
 * Every assertion:
 *   - increments the per-test assertion counter (so the runner can flag
 *     "risky" tests that assert nothing), and
 *   - throws an {@see AssertionFailedException} on failure, carrying a
 *     human-readable message.
 *
 * Consumed as a trait by {@see TestCase} so the assertions live on `$this`.
 */
trait Assert
{
    /** @var int Number of assertions made by the current test method. */
    protected int $numAssertions = 0;

    /** Number of assertions recorded so far (read by the runner). */
    public function numberOfAssertions(): int
    {
        return $this->numAssertions;
    }

    /** Reset the counter — called by the runner before each test method. */
    public function resetAssertionCount(): void
    {
        $this->numAssertions = 0;
    }

    // ── Boolean / null ──────────────────────────────────────────────────

    public function assertTrue($condition, string $message = ''): void
    {
        $this->recordAndCheck($condition === true, $message
            ?: 'Failed asserting that ' . $this->export($condition) . ' is true.');
    }

    public function assertFalse($condition, string $message = ''): void
    {
        $this->recordAndCheck($condition === false, $message
            ?: 'Failed asserting that ' . $this->export($condition) . ' is false.');
    }

    public function assertNull($value, string $message = ''): void
    {
        $this->recordAndCheck($value === null, $message
            ?: 'Failed asserting that ' . $this->export($value) . ' is null.');
    }

    public function assertNotNull($value, string $message = ''): void
    {
        $this->recordAndCheck($value !== null, $message
            ?: 'Failed asserting that a value is not null.');
    }

    // ── Equality / identity ─────────────────────────────────────────────

    public function assertEquals($expected, $actual, string $message = ''): void
    {
        $this->recordAndCheck($expected == $actual, $message
            ?: 'Failed asserting that ' . $this->export($actual)
               . ' matches expected ' . $this->export($expected) . '.');
    }

    public function assertNotEquals($expected, $actual, string $message = ''): void
    {
        $this->recordAndCheck($expected != $actual, $message
            ?: 'Failed asserting that ' . $this->export($actual)
               . ' does not match ' . $this->export($expected) . '.');
    }

    public function assertSame($expected, $actual, string $message = ''): void
    {
        $this->recordAndCheck($expected === $actual, $message
            ?: 'Failed asserting that ' . $this->export($actual)
               . ' is identical to ' . $this->export($expected) . '.');
    }

    public function assertNotSame($expected, $actual, string $message = ''): void
    {
        $this->recordAndCheck($expected !== $actual, $message
            ?: 'Failed asserting that two variables are not identical.');
    }

    // ── Emptiness / size ────────────────────────────────────────────────

    public function assertEmpty($value, string $message = ''): void
    {
        $this->recordAndCheck(empty($value), $message
            ?: 'Failed asserting that ' . $this->export($value) . ' is empty.');
    }

    public function assertNotEmpty($value, string $message = ''): void
    {
        $this->recordAndCheck(!empty($value), $message
            ?: 'Failed asserting that a value is not empty.');
    }

    public function assertCount(int $expected, $countable, string $message = ''): void
    {
        if (!is_countable($countable)) {
            $this->recordAndCheck(false, $message
                ?: 'Failed asserting that ' . $this->export($countable) . ' is countable.');
            return;
        }
        $actual = count($countable);
        $this->recordAndCheck($actual === $expected, $message
            ?: "Failed asserting that a countable of size {$actual} matches expected size {$expected}.");
    }

    // ── Containment ─────────────────────────────────────────────────────

    public function assertContains($needle, $haystack, string $message = ''): void
    {
        $found = is_array($haystack)
            ? in_array($needle, $haystack, true)
            : (is_string($haystack) && str_contains($haystack, (string) $needle));
        $this->recordAndCheck($found, $message
            ?: 'Failed asserting that ' . $this->export($haystack)
               . ' contains ' . $this->export($needle) . '.');
    }

    public function assertNotContains($needle, $haystack, string $message = ''): void
    {
        $found = is_array($haystack)
            ? in_array($needle, $haystack, true)
            : (is_string($haystack) && str_contains($haystack, (string) $needle));
        $this->recordAndCheck(!$found, $message
            ?: 'Failed asserting that a haystack does not contain ' . $this->export($needle) . '.');
    }

    public function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        $this->recordAndCheck(str_contains($haystack, $needle), $message
            ?: 'Failed asserting that ' . $this->export($haystack)
               . ' contains "' . $needle . '".');
    }

    public function assertArrayHasKey($key, array $array, string $message = ''): void
    {
        $this->recordAndCheck(array_key_exists($key, $array), $message
            ?: 'Failed asserting that an array has the key ' . $this->export($key) . '.');
    }

    public function assertArrayNotHasKey($key, array $array, string $message = ''): void
    {
        $this->recordAndCheck(!array_key_exists($key, $array), $message
            ?: 'Failed asserting that an array does not have the key ' . $this->export($key) . '.');
    }

    // ── Type / pattern / ordering ───────────────────────────────────────

    public function assertInstanceOf(string $expected, $actual, string $message = ''): void
    {
        $this->recordAndCheck($actual instanceof $expected, $message
            ?: 'Failed asserting that ' . $this->export($actual)
               . ' is an instance of ' . $expected . '.');
    }

    public function assertMatchesRegExp(string $pattern, string $subject, string $message = ''): void
    {
        $this->recordAndCheck(preg_match($pattern, $subject) === 1, $message
            ?: 'Failed asserting that ' . $this->export($subject)
               . ' matches pattern ' . $pattern . '.');
    }

    public function assertGreaterThan($expected, $actual, string $message = ''): void
    {
        $this->recordAndCheck($actual > $expected, $message
            ?: 'Failed asserting that ' . $this->export($actual)
               . ' is greater than ' . $this->export($expected) . '.');
    }

    public function assertLessThan($expected, $actual, string $message = ''): void
    {
        $this->recordAndCheck($actual < $expected, $message
            ?: 'Failed asserting that ' . $this->export($actual)
               . ' is less than ' . $this->export($expected) . '.');
    }

    // ── JSON ────────────────────────────────────────────────────────────

    public function assertJson(string $actualJson, string $message = ''): void
    {
        json_decode($actualJson);
        $this->recordAndCheck(json_last_error() === JSON_ERROR_NONE, $message
            ?: 'Failed asserting that a string is valid JSON.');
    }

    // ── Explicit outcomes ───────────────────────────────────────────────

    /**
     * Unconditionally fail the current test.
     */
    public function fail(string $message = ''): void
    {
        // Counts as an assertion so an all-fail test is never "risky".
        $this->numAssertions++;
        throw new AssertionFailedException($message ?: 'Test failed.');
    }

    /**
     * Skip the current test. The runner reports it separately and never
     * as a failure.
     */
    public function markTestSkipped(string $message = ''): void
    {
        throw new SkippedTestException($message ?: 'Test skipped.');
    }

    // ── Internals ───────────────────────────────────────────────────────

    private function recordAndCheck(bool $passed, string $message): void
    {
        $this->numAssertions++;
        if (!$passed) {
            throw new AssertionFailedException($message);
        }
    }

    private function export($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_scalar($value)) {
            return is_string($value) ? "'{$value}'" : (string) $value;
        }
        if (is_array($value)) {
            $json = json_encode($value);
            return $json === false ? 'Array' : $json;
        }
        if (is_object($value)) {
            return get_class($value);
        }
        return gettype($value);
    }
}
