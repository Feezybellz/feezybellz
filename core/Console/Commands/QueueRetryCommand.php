<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Queue\Queue;

/**
 * `php console queue:retry <id>` — push a failed job back onto its queue.
 * `php console queue:retry --all` — retry every failed job.
 */
class QueueRetryCommand extends Command
{
    protected string $signature = 'queue:retry';
    protected string $description = 'Re-queue failed job(s)';

    public function execute(): void
    {
        $id = $this->argument(0);
        $all = (bool) $this->option('all', false);

        if ($id === null && !$all) {
            $this->error('Provide a failed-job id, or --all to retry everything.');
            return;
        }

        $count = Queue::retryFailed($all ? null : $id);

        if ($count === 0) {
            $this->warn('No matching failed jobs found.');
            return;
        }

        $this->success("Re-queued {$count} job(s).");
    }
}
