<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Database\Migrator;

class MigrateCommand extends Command
{
    public function execute(): void
    {
        $this->info('Running migrations...');
        
        $migrator = new Migrator();
        
        try {
            // Debug: Check migration files
            $reflection = new \ReflectionClass($migrator);
            $pathProperty = $reflection->getProperty('migrationsPath');
            $pathProperty->setAccessible(true);
            $path = $pathProperty->getValue($migrator);
            $this->info("Migrations path: {$path}");
            
            $files = glob($path . '/*.php');
            $this->info("Found " . count($files) . " migration files");
            
            // Check executed migrations
            $method = $reflection->getMethod('getExecutedMigrations');
            $method->setAccessible(true);
            $executed = $method->invoke($migrator);
            $this->info("Already executed: " . count($executed) . " migrations");
            if (!empty($executed)) {
                foreach ($executed as $name) {
                    $this->info("  - {$name}");
                }
            }
            
            $migrated = $migrator->run();
            
            if (empty($migrated)) {
                $this->info('Nothing to migrate.');
                return;
            }
            
            foreach ($migrated as $migration) {
                $this->success("Migrated: {$migration}");
            }
            
            $this->success("\nMigration completed successfully!");
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }
}
