<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class ScheduleWorkCommand extends Command
{
    protected string $signature = 'schedule:work';
    protected string $description = 'Run the schedule worker daemon to continuously process scheduled tasks';

    public function execute(): void
    {
        $this->info("Starting Schedule Worker Daemon...");
        $this->info("Press Ctrl+C to exit.");

        while (true) {
            // Wait until the exact start of the next minute
            $now = time();
            $sleepTime = 60 - ($now % 60);
            
            sleep($sleepTime);

            $timestamp = date('Y-m-d H:i:s');
            $this->success("[{$timestamp}] Firing scheduled tasks...");
            
            // Determine the PHP binary dynamically
            $phpBinary = PHP_BINARY;
            $consolePath = dirname(dirname(dirname(dirname(__DIR__)))) . '/console';
            
            // Execute schedule:run completely asynchronously so long-running tasks 
            // don't freeze the daemon and miss the next minute!
            $command = sprintf('%s %s schedule:run > /dev/null 2>&1 &', escapeshellarg($phpBinary), escapeshellarg($consolePath));
            
            // Handle Windows environments
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $command = sprintf('start /B %s %s schedule:run > NUL', escapeshellarg($phpBinary), escapeshellarg($consolePath));
            }
            
            pclose(popen($command, 'r'));
        }
    }
}
