<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Queue\Queue;

class QueueWorkCommand extends Command
{
    protected static $defaultName = 'queue:work';
    protected static $description = 'Start processing jobs on the queue (Redis/Database)';

    public function execute(array $args): void
    {
        $queue = $args[0] ?? 'default';

        $this->info("Starting queue worker for connection: [{$queue}]...");
        $this->info("Press Ctrl+C to exit.");

        while (true) {
            $job = Queue::pop($queue);

            if ($job) {
                $callable = $job['callable'];
                $jobArgs = $job['args'] ?? [];

                $name = is_array($callable) ? implode('@', $callable) : $callable;
                $this->success("[" . date('Y-m-d H:i:s') . "] Processing: {$name}");

                try {
                    if (is_array($callable)) {
                        $class = new $callable[0]();
                        call_user_func_array([$class, $callable[1]], $jobArgs);
                    } else {
                        call_user_func_array($callable, $jobArgs);
                    }

                    $this->success("[" . date('Y-m-d H:i:s') . "] Processed: {$name}");
                } catch (\Throwable $e) {
                    $this->error("[" . date('Y-m-d H:i:s') . "] Failed: {$name}");
                    $this->error($e->getMessage());
                }
            } else {
                // Sleep for 1 second if no jobs available to prevent CPU spin
                sleep(1);
            }
        }
    }
}
