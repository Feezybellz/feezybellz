<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Database\Migrator;

class MigrateRollbackCommand extends Command
{
    public function execute(): void
    {
        $this->info('Rolling back migrations...');
        
        $migrator = new Migrator();
        
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
