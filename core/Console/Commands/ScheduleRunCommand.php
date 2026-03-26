<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Scheduling\Scheduler;
use Framework\Core\Application;

class ScheduleRunCommand extends Command
{
    // Update signature to accept an optional ID or Name
    protected string $signature = 'schedule:run {--id=} {--name=}';
    protected string $description = 'Run the scheduled tasks';

    public function execute(): void
    {
        $this->info("Checking for scheduled tasks...");

        $scheduler = new Scheduler();

        /**
         * 1. Dynamic Discovery: Load all Schedule files from App/Console
         */
        $schedulePath = Application::appPath('Console/Schedule');
        
        if (is_dir($schedulePath)) {
            $files = glob($schedulePath . '/*.php');

            foreach ($files as $file) {
                // Convert file path to Class Name (e.g., AfeezSchedule.php -> App\Console\AfeezSchedule)
                $className = 'App\\Console\\Schedule\\' . basename($file, '.php'); //

                if (class_exists($className)) {
                    $instance = new $className();
                    
                    // Check if the class has a build() method before calling it
                    if (method_exists($instance, 'build')) {
                        $instance->build($scheduler);
                    }
                }
            }
        } else {
            $this->error("Schedule directory not found at: {$schedulePath}");
            return;
        }

        /**
         * 2. Filtering Logic: Decide which events to run based on ID, Name, or Schedule
         */
        $targetId = $this->option('id');
        $targetName = $this->option('name');
        
        $eventsToRun = [];

        if ($targetId) {
            // Filter specifically for the ID
            $eventsToRun = array_filter($scheduler->getEvents(), function ($event) use ($targetId) {
                return $event->getId() === $targetId;
            });

            if (empty($eventsToRun)) {
                $this->warn("No task found with the identifier: {$targetId}");
                return;
            }
        } elseif ($targetName) {
            // Filter specifically for the Name
            $eventsToRun = array_filter($scheduler->getEvents(), function ($event) use ($targetName) {
                return $event->getName() === $targetName;
            });

            if (empty($eventsToRun)) {
                $this->warn("No task found with the name: {$targetName}");
                return;
            }
        } else {
            // Default behavior: Only run tasks that are actually due
            $eventsToRun = $scheduler->dueEvents();
        }

        if (empty($eventsToRun)) {
            $this->line("No tasks are due right now.");
            return;
        }
        
        /**
         * 3. Execution Loop
         */
        foreach ($eventsToRun as $event) {
            // Determine display description based on your preference
            $description = '';
            if ($targetId) {
                $description = $event->getId();
            } elseif ($targetName) {
                $description = $event->getName();
            } else {
                $description = $event->getName() ?? $event->getId() ?? 'Unnamed Task';
            }
            
            $description .= !empty($event->getDescription()) ? ' - ' . $event->getDescription() : '';   
            $this->line("Running task: " . $description);
            
            try {
                $event->run();
                $this->success("Task completed successfully.");
            } catch (\Exception $e) {
                $this->error("Task failed: " . $e->getMessage());
            }
        }
    }
}