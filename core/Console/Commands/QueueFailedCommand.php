<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Queue\Queue;

/**
 * `php console queue:failed` — list dead-lettered jobs.
 */
class QueueFailedCommand extends Command
{
    protected string $signature = 'queue:failed';
    protected string $description = 'List jobs that failed permanently';

    public function execute(): void
    {
        $failed = Queue::failedJobs();

        if ($failed === []) {
            $this->info('No failed jobs.');
            return;
        }

        foreach ($failed as $job) {
            $callable = is_array($job['callable'])
                ? implode('::', $job['callable'])
                : (string) $job['callable'];

            $this->line(str_repeat('-', 60));
            $this->line("id:        {$job['id']}");
            $this->line("queue:     {$job['queue']}");
            $this->line("callable:  {$callable}");
            $this->line("failed_at: {$job['failed_at']}");
            $this->error($job['error']);
        }

        $this->line(str_repeat('-', 60));
        $this->info(count($failed) . " failed job(s). Retry with `php console queue:retry <id>` (or `--all`).");
    }
}
