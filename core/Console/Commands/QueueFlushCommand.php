<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Queue\Queue;

/**
 * `php console queue:flush [id]` — delete failed job(s) permanently.
 * With no id, deletes ALL failed jobs.
 */
class QueueFlushCommand extends Command
{
    protected string $signature = 'queue:flush';
    protected string $description = 'Delete failed job(s) from the dead-letter store';

    public function execute(): void
    {
        $id = $this->argument(0);

        $count = Queue::flushFailed($id);

        if ($count === 0) {
            $this->info('No matching failed jobs.');
            return;
        }

        $this->success("Deleted {$count} failed job(s).");
    }
}
