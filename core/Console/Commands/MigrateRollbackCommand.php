<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Database\Migrator;

class MigrateRollbackCommand extends Command
{
    protected string $signature = 'migrate:rollback {--connection=} {--path=} {--steps=1} {--driver=} {--host=} {--port=} {--database=} {--username=} {--password=}';

    public function execute(): void
    {
        $connection = $this->option('connection');
        $path = $this->option('path');
        $steps = (int) $this->option('steps', 1);

        // Dynamic Connection Credentials
        $driver = $this->option('driver');
        $database = $this->option('database');
        
        if ($driver && $database) {
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
            $this->info("Rolling back {$steps} step(s) on connection: {$connection}...");
        } elseif (!$connection) {
            $this->info("Rolling back {$steps} step(s)...");
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
