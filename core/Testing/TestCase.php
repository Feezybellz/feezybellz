<?php

namespace Framework\Core\Testing;

use Framework\Core\Application;
use Framework\Core\Routing\Router;

/**
 * Base class for all test cases (unit tests).
 *
 * A fresh instance is created by the {@see TestRunner} for every test
 * method (xUnit semantics), so instance state never leaks between tests.
 * Assertions come from the {@see Assert} trait.
 *
 * For HTTP / feature tests, extend {@see HttpTestCase} instead — it adds
 * in-process request simulation and a fluent {@see TestResponse}.
 */
abstract class TestCase
{
    use Assert;

    protected $app;

    /** @var class-string<\Throwable>|null */
    private ?string $expectedException = null;
    private ?string $expectedExceptionMessage = null;

    /**
     * Setup run before each test method. Override in subclasses; call
     * parent::setUp() first if you do.
     */
    protected function setUp(): void
    {
        // Initialize the app using the project root.
        $this->app = new Application(dirname(__DIR__, 2));
        Router::setContainer($this->app);
    }

    /**
     * Teardown run after each test method (even if it failed). Override
     * to clean up resources.
     */
    protected function tearDown(): void
    {
    }

    /**
     * Template method the runner calls to execute one test method. Owns
     * the setUp → test → tearDown lifecycle and the expectException
     * bookkeeping so the runner stays a thin dispatcher.
     *
     * Exceptions propagate to the runner, which classifies them
     * (AssertionFailedException = failure, SkippedTestException = skipped,
     * anything else = error).
     */
    public function run(string $method): void
    {
        $this->resetAssertionCount();
        $this->expectedException = null;
        $this->expectedExceptionMessage = null;

        $this->setUp();

        try {
            $this->invokeTestMethod($method);
            $this->assertExpectedExceptionWasThrown();
        } catch (SkippedTestException $e) {
            throw $e;
        } catch (AssertionFailedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Did the test declare it expected this exception?
            if ($this->matchesExpectedException($e)) {
                $this->numAssertions++;
                return;
            }
            throw $e;
        } finally {
            $this->tearDown();
        }
    }

    /**
     * Declare that the test method is expected to throw the given
     * exception class. Optionally pin the message with
     * expectExceptionMessage().
     */
    protected function expectException(string $class): void
    {
        $this->expectedException = $class;
    }

    protected function expectExceptionMessage(string $message): void
    {
        $this->expectedExceptionMessage = $message;
    }

    // ── Internals ───────────────────────────────────────────────────────

    private function invokeTestMethod(string $method): void
    {
        $reflection = new \ReflectionMethod($this, $method);
        $reflection->setAccessible(true);
        $reflection->invoke($this);
    }

    private function matchesExpectedException(\Throwable $e): bool
    {
        if ($this->expectedException === null) {
            return false;
        }
        if (!($e instanceof $this->expectedException)) {
            return false;
        }
        if ($this->expectedExceptionMessage !== null
            && !str_contains($e->getMessage(), $this->expectedExceptionMessage)) {
            return false;
        }
        return true;
    }

    private function assertExpectedExceptionWasThrown(): void
    {
        if ($this->expectedException !== null) {
            throw new AssertionFailedException(
                'Failed asserting that exception of type "'
                . $this->expectedException . '" was thrown.'
            );
        }
    }
}
