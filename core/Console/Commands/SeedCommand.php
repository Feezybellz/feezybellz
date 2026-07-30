<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Database\DB;
use Framework\Core\Database\SeederRunner;

class SeedCommand extends Command
{
    /**
     * Execute the seed command
     * 
     * @return void
     */
    public function execute(): void
    {
        $seederName = $this->argument(0) ?? $this->option('class');
        
        // Custom db info parameters
        $driver = $this->option('driver');
        $host = $this->option('host');
        $port = $this->option('port');
        $username = $this->option('username');
        $password = $this->option('password');
        $database = $this->option('database');

        $connection = $this->option('connection');
        
        // Create an ad-hoc connection if specific DB options are passed
        if ($driver || $database) {
            $connection = $connection ?: 'dynamic_seed_conn';
            $config = config('db.connections.' . DB::getDefaultConnectionName()); // fallback to default config array structure
            
            if ($driver) $config['driver'] = $driver;
            if ($host) $config['host'] = $host;
            if ($port) $config['port'] = $port;
            if ($username) $config['username'] = $username;
            if ($password) $config['password'] = $password;
            if ($database) $config['database'] = $database;

            DB::addConnection($connection, $config);
            $this->info("Created dynamic connection '{$connection}' to database '{$database}'");
        }

        if ($connection) {
            $this->info("Seeding on connection: {$connection}");
        }

        $seedersPath = $this->option('path') 
            ? rtrim($this->option('path'), '/') 
            : dirname(dirname(dirname(__DIR__))) . '/database/seeders';

        if (!is_dir($seedersPath)) {
            $this->error("No seeders directory found at: {$seedersPath}");
            return;
        }

        // List available seeders
        if ($this->option('list')) {
            $this->listSeeders($seedersPath);
            return;
        }

        $runner = new SeederRunner($seedersPath, $connection);
        
        $this->info("Running seeders from: {$seedersPath}");
        
        try {
            $seeded = $runner->run($seederName);
            
            if (empty($seeded)) {
                $this->warn('No seeders were found to run.');
            } else {
                foreach ($seeded as $s) {
                    $this->success("Seeded: {$s}");
                }
                $this->line('');
                $this->success("Database seeding completed successfully.");
            }
        } catch (\Exception $e) {
            $this->error("Seeding failed: {$e->getMessage()}");
        }
    }

    /**
     * List all available seeders
     * 
     * @param string $seedersPath
     * @return void
     */
    protected function listSeeders(string $seedersPath): void
    {
        $files = glob($seedersPath . '/*.php');

        if (empty($files)) {
            $this->warn("No seeders found in {$seedersPath}");
            return;
        }

        $this->info('Available Seeders:');
        $this->line('');

        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $this->line("  \033[32m✓\033[0m {$className}");
        }

        $this->line('');
        $this->info('Usage:');
        $this->line("  php console db:seed                Run all seeders");
        $this->line("  php console db:seed <SeederName>   Run a specific seeder");
        $this->line("  php console db:seed --path=...     Run seeders from a custom path");
        $this->line("  php console db:seed --connection=.. Run seeders on a specific connection");
    }
}
