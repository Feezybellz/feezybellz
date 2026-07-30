<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Queue\Queue;
use Framework\Core\Queue\Worker;

/**
 * `php console queue:work [queue]` — process jobs from the Redis/Database
 * queue drivers with at-least-once delivery (reserve/ack, retries with
 * exponential backoff, dead-lettering).
 *
 *   php console queue:work                 # work the "default" queue
 *   php console queue:work emails          # work a named queue
 *   php console queue:work --once          # process at most one job, then exit
 *   php console queue:work --tries=5       # attempts before dead-lettering (default 3)
 *   php console queue:work --backoff=10    # base retry delay, doubles per attempt (default 5s)
 *   php console queue:work --sleep=5       # max idle wait per cycle (seconds)
 *
 * Idle waiting goes through the driver's awaitJob(): Redis blocks on a
 * notify token and wakes the instant work is pushed; the database driver
 * falls back to a bounded sleep. --sleep is therefore an upper bound on
 * idle latency, not a fixed pause.
 */
class QueueWorkCommand extends Command
{
    protected string $signature = 'queue:work';
    protected string $description = 'Start processing jobs on the queue (Redis/Database)';

    public function execute(): void
    {
        $queue = $this->argument(0, 'default');
        $once = (bool) $this->option('once', false);
        $sleep = max(1, (int) $this->option('sleep', 1));

        $worker = $this->makeWorker();

        $this->info("Starting queue worker for queue: [{$queue}]...");
        $this->info("Press Ctrl+C to exit.");

        while (true) {
            $result = $worker->runNextJob($queue);

            if ($once) {
                return;
            }

            if ($result === null) {
                // Idle — block on the driver's wakeup primitive (Redis
                // BLPOP) or its bounded-sleep fallback. No busy polling.
                Queue::driver()->awaitJob($queue, $sleep);
            }
        }
    }

    /**
     * Build the Worker from CLI options. Split out so tests can construct
     * the command and inspect the wiring without entering the loop.
     */
    public function makeWorker(): Worker
    {
        return new Worker(
            Queue::driver(),
            max(1, (int) $this->option('tries', 3)),
            max(0, (int) $this->option('backoff', 5)),
            function (string $level, string $message) {
                switch ($level) {
                    case 'success': $this->success($message); break;
                    case 'error':   $this->error($message); break;
                    default:        $this->line($message); break;
                }
            }
        );
    }
}
