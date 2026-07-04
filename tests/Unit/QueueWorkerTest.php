<?php

namespace Tests\Unit;

use Framework\Core\Console\Commands\QueueWorkCommand;
use Framework\Core\Events\Dispatcher;
use Framework\Core\Queue\Worker;
use Framework\Core\Testing\TestCase;
use Tests\Fixtures\ArrayQueueDriver;
use Tests\Fixtures\FakeUserRegistered;
use Tests\Fixtures\RecordingListener;

/**
 * End-to-end check of the worker path (§8.1.1 + §8.1.3 + §8.2): the exact
 * payload the Dispatcher pushes for a queued listener, after a JSON
 * round-trip, must run and ack through the Worker.
 */
class QueueWorkerTest extends TestCase
{
    private ArrayQueueDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new ArrayQueueDriver();
        RecordingListener::$handled = [];
    }

    protected function tearDown(): void
    {
        \Framework\Core\Queue\Queue::reset();
    }

    public function test_queued_listener_payload_runs_and_acks(): void
    {
        // What Dispatcher::dispatch() pushes for a ShouldQueue listener, as
        // it looks after the queue driver's json_encode/json_decode cycle.
        $wire = json_decode(json_encode([
            'callable' => [Dispatcher::class, 'handleQueuedListener'],
            'args' => [
                RecordingListener::class,
                FakeUserRegistered::class,
                get_object_vars(new FakeUserRegistered(9, 'lin@example.com')),
            ],
        ]), true);

        $this->driver->push('default', $wire['callable'], $wire['args']);

        $worker = new Worker($this->driver);
        $this->assertTrue($worker->runNextJob('default'));

        $this->assertCount(1, RecordingListener::$handled);
        $this->assertSame(9, RecordingListener::$handled[0]->userId);
        $this->assertEmpty($this->driver->reserved); // acked
    }

    public function test_missing_listener_class_is_dead_lettered(): void
    {
        $this->driver->push('default', [Dispatcher::class, 'handleQueuedListener'], [
            '\Nope\MissingListener', FakeUserRegistered::class, [],
        ]);

        $worker = new Worker($this->driver, 1, 0);
        $this->assertFalse($worker->runNextJob('default'));

        $this->assertCount(1, $this->driver->failed);
        $this->assertStringContainsString('MissingListener', $this->driver->failed[0]['error']);
    }

    public function test_command_builds_worker_from_options(): void
    {
        // Inject the in-memory driver so Queue::driver() doesn't try to
        // open a real Redis/DB connection inside the test.
        \Framework\Core\Queue\Queue::setDriver($this->driver);

        $cmd = new QueueWorkCommand(['console', 'queue:work', '--tries=7', '--backoff=2', '--silent']);
        $worker = $cmd->makeWorker();

        $this->assertInstanceOf(Worker::class, $worker);

        $ref = new \ReflectionClass($worker);
        $tries = $ref->getProperty('tries');
        $tries->setAccessible(true);
        $backoff = $ref->getProperty('backoff');
        $backoff->setAccessible(true);

        $this->assertSame(7, $tries->getValue($worker));
        $this->assertSame(2, $backoff->getValue($worker));
    }
}
