<?php

namespace Framework\Core\Scheduling;

class Scheduler
{
    protected $events = [];

    /**
     * Schedule a Closure to run.
     */
    public function call(callable $callback): Event
    {
        $event = new Event($callback);
        $this->events[] = $event;
        return $event;
    }

    /**
     * Schedule an existing Console Command to run.
     */
    public function command(string $commandClass): Event
    {
        return $this->call(function () use ($commandClass) {
            $instance = new $commandClass();
            // Assuming your commands have an execute() method
            return $instance->execute(); 
        });
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * Get all events that are currently due
     */
    public function dueEvents(): array
    {
        return array_filter($this->events, function (Event $event) {
            return $event->isDue();
        });
    }

}
