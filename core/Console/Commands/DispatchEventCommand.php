<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Events\Dispatcher;

class DispatchEventCommand extends Command
{
    protected string $signature = 'event';
    protected string $description = 'Dispatch a specific event class via CLI';

    public function execute(): void
    {
        // Retrieve the --class option from the CLI
        $eventClass = $this->option('class');

        if (!$eventClass) {
            $this->error("Please specify an event class using --class=ClassName");
            return;
        }

        if (!class_exists($eventClass)) {
            $this->error("The event class '{$eventClass}' does not exist.");
            return;
        }

        $this->info("Dispatching event: {$eventClass}...");

        try {
            // Instantiate the event and dispatch it
            $eventInstance = new $eventClass();
            Dispatcher::dispatch($eventInstance);
            $this->success("Event dispatched successfully.");
        } catch (\Throwable $e) {
            $this->error("Failed to dispatch event: " . $e->getMessage());
        }
    }
}