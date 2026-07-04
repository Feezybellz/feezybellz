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
     *
     * @param string $commandClass FQCN of a Command subclass.
     * @param array  $args         Extra CLI-style arguments/options, e.g.
     *                             ['emails', '--once'] — parsed exactly as
     *                             if typed after the command name.
     */
    public function command(string $commandClass, array $args = []): Event
    {
        return $this->call(function () use ($commandClass, $args) {
            // Command::__construct(array $argv) expects real argv shape:
            // [script, commandName, ...args] — parseArguments() reads from
            // index 2 onward. (`new $commandClass()` used to fatal with an
            // ArgumentCountError here.)
            $instance = new $commandClass(array_merge(['console', $commandClass], $args));
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
