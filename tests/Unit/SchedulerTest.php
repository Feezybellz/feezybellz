<?php

namespace Tests\Unit;

use Framework\Core\Scheduling\Scheduler;
use Framework\Core\Scheduling\Event;
use Framework\Core\Testing\TestCase;
use Tests\Fixtures\MarkerCommand;

/**
 * Covers Scheduler::command() (remaining.md §8.1.4): it must construct
 * Command subclasses with a real argv array — `new $class()` used to throw
 * an ArgumentCountError because Command::__construct requires $argv.
 */
class SchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        MarkerCommand::$ran = false;
        MarkerCommand::$seenArguments = [];
    }

    public function test_scheduled_command_runs(): void
    {
        $scheduler = new Scheduler();
        $event = $scheduler->command(MarkerCommand::class);

        $this->assertInstanceOf(Event::class, $event);

        $event->run();

        $this->assertTrue(MarkerCommand::$ran);
    }

    public function test_scheduled_command_receives_arguments(): void
    {
        $scheduler = new Scheduler();
        $scheduler->command(MarkerCommand::class, ['emails'])->run();

        $this->assertSame(['emails'], MarkerCommand::$seenArguments);
    }

    public function test_scheduled_closure_runs(): void
    {
        $ran = false;
        $scheduler = new Scheduler();
        $scheduler->call(function () use (&$ran) {
            $ran = true;
        })->run();

        $this->assertTrue($ran);
    }

    public function test_due_events_filtering(): void
    {
        $scheduler = new Scheduler();
        $scheduler->call(fn () => null)->everyMinute();      // always due
        $scheduler->call(fn () => null)->cron('0 0 31 2 *'); // never due (Feb 31)

        $this->assertCount(2, $scheduler->getEvents());
        $this->assertCount(1, $scheduler->dueEvents());
    }
}
