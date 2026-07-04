<?php

namespace Tests\Unit;

use Framework\Core\Database\DB;
use Framework\Core\Queue\Drivers\DatabaseDriver;
use Framework\Core\Testing\TestCase;

/**
 * At-least-once delivery semantics of the DatabaseDriver (remaining.md
 * §8.2.1/8.2.2/8.2.3), run against an in-memory SQLite database.
 */
class DatabaseQueueTest extends TestCase
{
    private DatabaseDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // Fresh in-memory DB per test: purging closes the previous PDO
        // handle, so the next connection starts a brand-new :memory: store.
        DB::purge('default');
        DB::addConnection('default', ['driver' => 'sqlite', 'database' => ':memory:']);

        DB::connection()->query(
            "CREATE TABLE _framework_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue TEXT, payload TEXT, attempts INTEGER DEFAULT 0,
                reserved_at INTEGER NULL, available_at INTEGER NULL,
                created_at TEXT
            )", []
        );
        DB::connection()->query(
            "CREATE TABLE _framework_failed_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue TEXT, payload TEXT, error TEXT, failed_at TEXT
            )", []
        );

        $this->driver = new DatabaseDriver(['retry_after' => 90]);
    }

    protected function tearDown(): void
    {
        DB::purge('default');
    }

    public function test_pop_reserves_instead_of_deleting(): void
    {
        $this->driver->push('default', 'strlen', ['abc']);

        $job = $this->driver->pop('default');
        $this->assertNotNull($job);
        $this->assertSame('strlen', $job['callable']);
        $this->assertSame(['abc'], $job['args']);
        $this->assertSame(1, $job['attempts']);

        // Reserved job is invisible to a second worker...
        $this->assertNull($this->driver->pop('default'));

        // ...but still exists in the table (not deleted).
        $this->assertSame(1, DB::table('_framework_jobs')->count());
    }

    public function test_ack_removes_the_job_permanently(): void
    {
        $this->driver->push('default', 'strlen', ['abc']);
        $job = $this->driver->pop('default');

        $this->driver->ack($job);

        $this->assertSame(0, DB::table('_framework_jobs')->count());
        $this->assertNull($this->driver->pop('default'));
    }

    public function test_release_makes_the_job_claimable_again(): void
    {
        $this->driver->push('default', 'strlen', ['abc']);
        $job = $this->driver->pop('default');

        $this->driver->release($job, 0);

        $again = $this->driver->pop('default');
        $this->assertNotNull($again);
        $this->assertSame(2, $again['attempts']);
    }

    public function test_release_with_delay_defers_availability(): void
    {
        $this->driver->push('default', 'strlen', ['abc']);
        $job = $this->driver->pop('default');

        $this->driver->release($job, 3600);
        $this->assertNull($this->driver->pop('default'));

        // Time-travel: pull availability into the past.
        DB::table('_framework_jobs')->where('id', '=', $job['id'])
            ->update(['available_at' => time() - 1]);

        $this->assertNotNull($this->driver->pop('default'));
    }

    public function test_expired_reservation_is_reclaimed(): void
    {
        $this->driver->push('default', 'strlen', ['abc']);
        $job = $this->driver->pop('default');
        $this->assertNull($this->driver->pop('default')); // reserved

        // Simulate a crashed worker: reservation older than retry_after.
        DB::table('_framework_jobs')->where('id', '=', $job['id'])
            ->update(['reserved_at' => time() - 200]);

        $reclaimed = $this->driver->pop('default');
        $this->assertNotNull($reclaimed);
        $this->assertSame(2, $reclaimed['attempts']);
    }

    public function test_later_defers_availability(): void
    {
        $this->driver->later(3600, 'default', 'strlen', ['abc']);
        $this->assertNull($this->driver->pop('default'));

        DB::table('_framework_jobs')->update(['available_at' => time() - 1]);
        $this->assertNotNull($this->driver->pop('default'));
    }

    public function test_fail_moves_job_to_dead_letter_store(): void
    {
        $this->driver->push('default', 'strlen', ['abc']);
        $job = $this->driver->pop('default');

        $this->driver->fail($job, 'RuntimeException: boom at worker.php:1');

        $this->assertSame(0, DB::table('_framework_jobs')->count());

        $failed = $this->driver->failedJobs();
        $this->assertCount(1, $failed);
        $this->assertSame('strlen', $failed[0]['callable']);
        $this->assertStringContainsString('boom', $failed[0]['error']);
    }

    public function test_retry_failed_requeues_with_reset_attempts(): void
    {
        $this->driver->push('default', 'strlen', ['abc']);
        $this->driver->fail($this->driver->pop('default'), 'boom');

        $requeued = $this->driver->retryFailed();
        $this->assertSame(1, $requeued);
        $this->assertEmpty($this->driver->failedJobs());

        $job = $this->driver->pop('default');
        $this->assertNotNull($job);
        $this->assertSame(1, $job['attempts']); // counter was reset
    }

    public function test_flush_failed_deletes(): void
    {
        $this->driver->push('default', 'strlen', ['a']);
        $this->driver->fail($this->driver->pop('default'), 'x');
        $this->driver->push('default', 'strrev', ['b']);
        $this->driver->fail($this->driver->pop('default'), 'y');

        $this->assertSame(2, $this->driver->flushFailed());
        $this->assertEmpty($this->driver->failedJobs());
    }

    public function test_queues_are_isolated(): void
    {
        $this->driver->push('emails', 'strlen', ['a']);

        $this->assertNull($this->driver->pop('default'));
        $this->assertNotNull($this->driver->pop('emails'));
    }
}
