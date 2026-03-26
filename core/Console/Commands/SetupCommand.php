<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class SetupCommand extends Command
{
    protected string $signature = 'setup {--user=} {--group=}';
    protected string $description = 'Initialize framework directories and basic configuration';

    public function execute(): void
    {
        $this->info("Setting up framework environment...\n");

        // FIX: Go up exactly 3 levels from core/Console/Commands to reach the 'pos' root
        $basePath = dirname(__DIR__, 3);

        // 1. Resolve User and Group
        $targetUser = $this->option('user') ?: $this->getWebUser();
        $targetGroup = $this->option('group') ?: $targetUser; // Default group to user name if not provided
        
        if (!$basePath) {
            $this->error("Could not determine base path.");
            return;
        }

        // Define all the directories your framework needs to function
        $directories = [
            '/storage/logs',                 // For our Logger
            '/storage/framework/sessions',   // For file-based sessions
            '/storage/framework/views',      // For compiled template caches
            '/storage/framework/cache',      // For general app caching
            '/storage/app/public',           // For secure file uploads
            '/public/uploads',               // For public-facing image uploads,
            '/storage/framework/testing',    // For test results
        ];

        foreach ($directories as $dir) {
            $fullPath = $basePath . $dir;
            
            if (!is_dir($fullPath)) {
                // Create directory with 0775 permissions
                if (mkdir($fullPath, 0775, true)) {
                    $this->success("Created: {$dir}");
                    $this->createGitIgnore($fullPath);
                } else {
                    $this->error("Failed to create: {$dir} (Check permissions)");
                }
            } else {
                $this->line("Exists:  {$dir}");
            }
            // Apply ownership and permissions
            $this->setPermissions($fullPath, $targetUser, $targetGroup);
        }

        echo "\n";
        $this->info("Setup complete! Your framework is ready to run.");
    }

    /**
     * Set directory permissions, ownership, and group
    **/
    protected function setPermissions(string $path, string $user, string $group): void
    {
        try {
            // 1. Set ownership for the directory itself
            chown($path, $user);
            chgrp($path, $group);
            chmod($path, 02775);

            // 2. NEW: Recursive fix for existing files/folders
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                chown($item->getPathname(), $user);
                chgrp($item->getPathname(), $group);
                
                if ($item->isDir()) {
                    chmod($item->getPathname(), 02775);
                } else {
                    // Files should usually be 0664 (Owner & Group can write)
                    chmod($item->getPathname(), 0664);
                }
            }
            
            $this->line("Permissions [Recursive] set for: " . basename($path));
        } catch (\Exception $e) {
            $this->warn("Permission Error: Use 'sudo' for " . basename($path));
        }
    }

    protected function getWebUser(): string
    {
        $users = ['www-data', 'apache', '_www', 'nobody', 'nginx', 'http'];
        foreach ($users as $user) {
            if (posix_getpwnam($user)) return $user;
        }
        return get_current_user();
    }

    protected function createGitIgnore(string $path): void
    {
        $content = "*\n!.gitignore\n";
        file_put_contents($path . '/.gitignore', $content);
    }
}