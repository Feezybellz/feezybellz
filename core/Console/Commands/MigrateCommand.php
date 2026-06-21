<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Database\Migrator;

class MigrateCommand extends Command
{
    protected string $signature = 'migrate {--connection=} {--path=} {--driver=} {--host=} {--port=} {--database=} {--username=} {--password=}';

    public function execute(): void
    {
        $connection = $this->option('connection');
        $path = $this->option('path');

        // Dynamic Connection Credentials
        $driver = $this->option('driver');
        $database = $this->option('database');
        
        if ($driver && $database) {
            // We are creating an "on-the-fly" connection!
            $connection = $connection ?: 'dynamic_migration_conn';
            
            $dbConfig = [
                'driver'   => $driver,
                'host'     => $this->option('host', '127.0.0.1'),
                'port'     => $this->option('port', '3306'),
                'database' => $database,
                'username' => $this->option('username', 'root'),
                'password' => $this->option('password', ''),
                'charset'  => 'utf8mb4',
            ];

            config(["database.connections.{$connection}" => $dbConfig]);
            \Framework\Core\Database\DB::addConnection($connection, $dbConfig);
            
            $this->info("Dynamically configured connection: {$connection} -> {$database}");
        }

        if ($connection && !$driver) {
            $this->info("Running migrations on configured connection: {$connection}...");
        } elseif (!$connection) {
            $this->info('Running migrations on default connection...');
        }

        if ($path) {
            $this->info("Using custom migrations path: {$path}");
        }
        
        $migrator = new Migrator($path, $connection);
        
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
