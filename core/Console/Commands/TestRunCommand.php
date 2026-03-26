<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class TestRunCommand extends Command
{
    protected string $signature = 'test';
    protected string $description = 'Run all application tests recursively';

    public function execute(): void
    {
        $this->info("🚀 Initializing Test Suite...");
        
        $testDir = dirname(__DIR__, 3) . '/storage/framework/testing';
        
        // 1. Find ALL files ending in Test.php recursively
        $directory = new RecursiveDirectoryIterator($testDir);
        $iterator = new RecursiveIteratorIterator($directory);
        $testFiles = new RegexIterator($iterator, '/^.+Test\.php$/i', RegexIterator::GET_MATCH);

        $passed = 0;
        $failed = 0;

        foreach ($testFiles as $file) {
            $filePath = $file[0];
            require_once $filePath;

            // 2. Determine class name based on file path (assumes PSR-4)
            // Example: /tests/Feature/UserTest.php -> Tests\Feature\UserTest
            $relative = str_replace([$testDir, '.php', '/'], ['', '', '\\'], $filePath);
            $className = "Tests" . $relative;

            if (!class_exists($className)) {
                $this->error("Could not find class: {$className} in {$filePath}");
                continue;
            }

            $testInstance = new $className();
            $this->line("\nRunning: " . $className);

            $methods = get_class_methods($testInstance);
            foreach ($methods as $method) {
                if (str_starts_with($method, 'test')) {
                    try {
                        // 3. Always run setUp if it exists
                        if (method_exists($testInstance, 'setUp')) {
                            $testInstance->setUp();
                        }

                        $testInstance->$method();
                        $passed++;
                    } catch (\Throwable $e) {
                        $this->error("  ❌ {$method} failed: " . $e->getMessage());
                        $failed++;
                    }
                }
            }
        }

        $this->line("\n" . str_repeat('=', 30));
        $this->info("Tests Complete: {$passed} passed, {$failed} failed.");
    }
}