<?php

namespace Tests\Unit;

use Framework\Core\Events\Dispatcher;
use Framework\Core\Queue\Drivers\DatabaseDriver;
use Framework\Core\Testing\TestCase;
use Tests\Fixtures\FakeUserRegistered;
use Tests\Fixtures\RecordingListener;

/**
 * Covers the Dispatcher, including the queued-listener wire format fix
 * (remaining.md §8.1.3): payloads must survive a JSON round-trip, and the
 * worker-side entry point must rebuild the event and run the listener.
 */
class DispatcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Dispatcher::flush();
        RecordingListener::$handled = [];
    }

    protected function tearDown(): void
    {
        Dispatcher::flush();
    }

    public function test_sync_class_listener_receives_event(): void
    {
        Dispatcher::listen(FakeUserRegistered::class, RecordingListener::class);

        Dispatcher::dispatch(new FakeUserRegistered(7, 'ada@example.com'));

        $this->assertCount(1, RecordingListener::$handled);
        $this->assertSame(7, RecordingListener::$handled[0]->userId);
    }

    public function test_closure_listener_receives_event(): void
    {
        $seen = null;
        Dispatcher::listen(FakeUserRegistered::class, function ($event) use (&$seen) {
            $seen = $event;
        });

        Dispatcher::dispatch(new FakeUserRegistered(1, 'x@example.com'));

        $this->assertNotNull($seen);
        $this->assertSame('x@example.com', $seen->email);
    }

    public function test_queued_listener_round_trips_through_json(): void
    {
        // Simulate exactly what a queue driver + worker do to the payload
        // the Dispatcher pushes: json_encode → json_decode → invoke.
        $callable = [Dispatcher::class, 'handleQueuedListener'];
        $args = [
            RecordingListener::class,
            FakeUserRegistered::class,
            get_object_vars(new FakeUserRegistered(42, 'grace@example.com')),
        ];

        $wire = json_decode(json_encode(['callable' => $callable, 'args' => $args]), true);

        // The callable must survive as plain strings (the old code pushed an
        // object instance here, which JSON cannot represent).
        $this->assertSame([Dispatcher::class, 'handleQueuedListener'], $wire['callable']);

        call_user_func_array(
            [$wire['callable'][0], $wire['callable'][1]],
            $wire['args']
        );

        $this->assertCount(1, RecordingListener::$handled);
        $event = RecordingListener::$handled[0];
        $this->assertInstanceOf(FakeUserRegistered::class, $event);
        $this->assertSame(42, $event->userId);
        $this->assertSame('grace@example.com', $event->email);
    }

    public function test_handle_queued_listener_rejects_unknown_classes(): void
    {
        $this->expectException(\RuntimeException::class);
        Dispatcher::handleQueuedListener('\Nope\Missing', FakeUserRegistered::class, []);
    }

    public function test_drivers_reject_object_instance_callables(): void
    {
        // Defense-in-depth guard: an object hidden inside an array callable
        // must be refused at push time, not mangled by json_encode.
        $driver = new DatabaseDriver([]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot push closures or objects');
        $driver->push('default', [new RecordingListener(), 'handle'], []);
    }

    public function test_flush_removes_listeners(): void
    {
        Dispatcher::listen(FakeUserRegistered::class, RecordingListener::class);
        Dispatcher::flush();

        Dispatcher::dispatch(new FakeUserRegistered(1, 'y@example.com'));

        $this->assertEmpty(RecordingListener::$handled);
    }
}
