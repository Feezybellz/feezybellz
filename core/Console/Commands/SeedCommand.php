<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class SeedCommand extends Command
{
    /**
     * Execute the seed command
     * 
     * @return void
     */
    public function execute(): void
    {
        $seederName = $this->argument(0);
        $seedersPath = dirname(dirname(dirname(__DIR__))) . '/database/seeders';

        if (!is_dir($seedersPath)) {
            $this->error('No seeders directory found.');
            return;
        }

        // List available seeders
        if ($this->option('list')) {
            $this->listSeeders($seedersPath);
            return;
        }

        // If a specific seeder is provided, run only that one
        if ($seederName) {
            $this->runSeeder($seedersPath, $seederName);
            return;
        }

        // Run all seeders in the directory
        $this->runAllSeeders($seedersPath);
    }

    /**
     * Run a specific seeder by name
     * 
     * @param string $seedersPath
     * @param string $seederName
     * @return void
     */
    protected function runSeeder(string $seedersPath, string $seederName): void
    {
        $filePath = $seedersPath . '/' . $seederName . '.php';

        if (!file_exists($filePath)) {
            $this->error("Seeder not found: {$seederName}");
            $this->info("Expected file: database/seeders/{$seederName}.php");
            return;
        }

        $this->runSeederFile($filePath, $seederName);
    }

    /**
     * Run a seeder file
     * 
     * @param string $filePath
     * @param string $className
     * @return void
     */
    protected function runSeederFile(string $filePath, string $className): void
    {
        require_once $filePath;

        if (!class_exists($className)) {
            $this->error("Class '{$className}' not found in {$filePath}");
            return;
        }

        if (!is_subclass_of($className, \Framework\Core\Database\Seeder::class)) {
            $this->error("Class '{$className}' must extend Framework\\Core\\Database\\Seeder");
            return;
        }

        $this->info("Seeding: {$className}");
        $this->line('');

        try {
            $seeder = new $className();
            $seeder->run();
            $this->line('');
            $this->success("Database seeding completed successfully.");
        } catch (\Exception $e) {
            $this->error("Seeding failed: {$e->getMessage()}");
        }
    }

    /**
     * Run all seeder files in the seeders directory
     * 
     * @param string $seedersPath
     * @return void
     */
    protected function runAllSeeders(string $seedersPath): void
    {
        $files = glob($seedersPath . '/*.php');
        $count = 0;

        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            
            require_once $file;

            if (!class_exists($className)) {
                $this->warn("Skipping {$className}: class not found.");
                continue;
            }

            if (!is_subclass_of($className, \Framework\Core\Database\Seeder::class)) {
                $this->warn("Skipping {$className}: does not extend Seeder.");
                continue;
            }

            $this->info("Seeding: {$className}");
            try {
                $seeder = new $className();
                $seeder->run();
                $count++;
                $this->success("Seeded: {$className}");
                $this->line('');
            } catch (\Exception $e) {
                $this->error("Failed to seed {$className}: {$e->getMessage()}");
            }
        }

        if ($count > 0) {
            $this->success("Ran {$count} seeder(s) successfully.");
        } else {
            $this->warn('No seeders were found to run.');
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
            $this->warn('No seeders found in database/seeders/');
            return;
        }

        $this->info('Available Seeders:');
        $this->line('');

        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);

            require_once $file;

            $isValid = class_exists($className) && is_subclass_of($className, \Framework\Core\Database\Seeder::class);
            $status = $isValid ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m";

            $this->line("  {$status} {$className}");
        }

        $this->line('');
        $this->info('Usage:');
        $this->line("  php console db:seed                Run all seeders");
        $this->line("  php console db:seed <SeederName>   Run a specific seeder");
    }
}
