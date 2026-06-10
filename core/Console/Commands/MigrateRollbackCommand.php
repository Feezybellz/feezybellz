<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Database\Migrator;

class MigrateRollbackCommand extends Command
{
    public function execute(): void
    {
        $connection = $this->option('connection');
        $path = $this->option('path');

        if ($connection) {
            $this->info("Rolling back migrations on connection: {$connection}...");
        } else {
            $this->info('Rolling back migrations...');
        }

        if ($path) {
            $this->info("Using custom migrations path: {$path}");
        }
        
        $migrator = new Migrator($path, $connection);
        
        try {
            $steps = (int) $this->option('step', 1);
            $rolledBack = $migrator->rollback($steps);
            
            if (empty($rolledBack)) {
                $this->info('Nothing to rollback.');
                return;
            }
            
            foreach ($rolledBack as $migration) {
                $this->success("Rolled back: {$migration}");
            }
            
            $this->success("\nRollback completed successfully!");
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
