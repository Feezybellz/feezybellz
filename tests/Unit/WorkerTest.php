<?php

namespace Tests\Unit;

use Framework\Core\Queue\Worker;
use Framework\Core\Testing\TestCase;
use Tests\Fixtures\ArrayQueueDriver;
use Tests\Fixtures\FlakyJob;

/**
 * Worker lifecycle: pop → run → ack / release-with-backoff / dead-letter
 * (remaining.md §8.2.2).
 */
class WorkerTest extends TestCase
{
    private ArrayQueueDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new ArrayQueueDriver();
        FlakyJob::$calls = 0;
        FlakyJob::$failures = 0;
    }

    private function worker(int $tries = 3, int $backoff = 5): Worker
    {
        return new Worker($this->driver, $tries, $backoff);
    }

    public function test_successful_job_is_acked(): void
    {
        $this->driver->push('default', [FlakyJob::class, 'attempt']);

        $result = $this->worker()->runNextJob('default');

        $this->assertTrue($result);
        $this->assertSame(1, FlakyJob::$calls);
        $this->assertEmpty($this->driver->reserved);
        $this->assertSame(0, $this->driver->readyCount());
        $this->assertEmpty($this->driver->failed);
    }

    public function test_empty_queue_returns_null(): void
    {
        $this->assertNull($this->worker()->runNextJob('default'));
    }

    public function test_failed_job_is_released_with_exponential_backoff(): void
    {
        FlakyJob::$failures = 99; // always fails
        $this->driver->push('default', [FlakyJob::class, 'attempt']);

        // Attempt 1 → release with base backoff (5 * 2^0 = 5s).
        $this->assertFalse($this->worker()->runNextJob('default'));
        $this->assertCount(1, $this->driver->releases);
        $this->assertSame(5, $this->driver->releases[0]['delay']);
        $this->assertEmpty($this->driver->failed);

        // Make it claimable now and run attempt 2 → 5 * 2^1 = 10s.
        $this->driver->ready['default'][0]['available_at'] = time();
        $this->assertFalse($this->worker()->runNextJob('default'));
        $this->assertSame(10, $this->driver->releases[1]['delay']);
    }

    public function test_exhausted_job_is_dead_lettered_with_the_exception(): void
    {
        FlakyJob::$failures = 99;
        $this->driver->push('default', [FlakyJob::class, 'attempt']);

        $worker = $this->worker(3, 0); // zero backoff → immediately claimable
        $this->assertFalse($worker->runNextJob('default')); // attempt 1 → release
        $this->assertFalse($worker->runNextJob('default')); // attempt 2 → release
        $this->assertFalse($worker->runNextJob('default')); // attempt 3 → fail

        $this->assertSame(0, $this->driver->readyCount());
        $this->assertEmpty($this->driver->reserved);
        $this->assertCount(1, $this->driver->failed);
        $this->assertStringContainsString('RuntimeException', $this->driver->failed[0]['error']);
        $this->assertStringContainsString('flaky failure #3', $this->driver->failed[0]['error']);
    }

    public function test_job_that_recovers_before_exhaustion_succeeds(): void
    {
        FlakyJob::$failures = 2; // fails twice, succeeds on third call
        $this->driver->push('default', [FlakyJob::class, 'attempt']);

        $worker = $this->worker(5, 0);
        $this->assertFalse($worker->runNextJob('default'));
        $this->assertFalse($worker->runNextJob('default'));
        $this->assertTrue($worker->runNextJob('default'));

        $this->assertSame(3, FlakyJob::$calls);
        $this->assertEmpty($this->driver->failed);
        $this->assertSame(0, $this->driver->readyCount());
    }

    public function test_static_method_callable_runs_without_instantiation(): void
    {
        $this->driver->push('default', [FlakyJob::class, 'staticAttempt']);

        $this->assertTrue($this->worker()->runNextJob('default'));
        $this->assertSame(1, FlakyJob::$calls);
    }

    public function test_plain_function_callable_runs(): void
    {
        $this->driver->push('default', 'strlen', ['hello']);

        $this->assertTrue($this->worker()->runNextJob('default'));
    }

    public function test_missing_class_is_eventually_dead_lettered(): void
    {
        $this->driver->push('default', ['\Nope\MissingJob', 'run']);

        $worker = $this->worker(2, 0);
        $this->assertFalse($worker->runNextJob('default'));
        $this->assertFalse($worker->runNextJob('default'));

        $this->assertCount(1, $this->driver->failed);
        $this->assertStringContainsString('Job class not found', $this->driver->failed[0]['error']);
    }

    public function test_malformed_payload_is_handled_not_fatal(): void
    {
        $this->driver->push('default', null);

        $this->assertFalse($this->worker(1, 0)->runNextJob('default'));
        $this->assertCount(1, $this->driver->failed);
        $this->assertStringContainsString('no runnable callable', $this->driver->failed[0]['error']);
    }

    public function test_retry_failed_gives_the_job_a_fresh_start(): void
    {
        FlakyJob::$failures = 1; // fails once, then succeeds
        $this->driver->push('default', [FlakyJob::class, 'attempt']);

        $this->assertFalse($this->worker(1, 0)->runNextJob('default')); // dead-lettered
        $this->assertCount(1, $this->driver->failed);

        $this->assertSame(1, $this->driver->retryFailed());
        $this->assertTrue($this->worker(1, 0)->runNextJob('default'));  // succeeds now
        $this->assertEmpty($this->driver->failed);
    }
}
