<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class SystemPermissionsCommand extends Command
{
    /**
     * Execute the command
     */
    public function execute(): void
    {
        $this->info("=== System Permissions Helper ===");
        
        $basePath = dirname(__DIR__, 3);
        $webUser = $this->detectWebUser();
        $currentUser = get_current_user();

        $this->line("Current User: \033[33m" . $currentUser . "\033[0m");
        $this->line("Detected Web User: \033[33m" . $webUser . "\033[0m");
        $this->line("Base Path: " . $basePath);
        $this->line("");

        $directories = [
            'storage',
            'storage/logs',
            'storage/framework',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/framework/cache',
            'bootstrap/cache',
            'public/uploads'
        ];

        $this->info("Step 1: Status Checklist");
        $this->line("--------------------------------------------------");
        
        foreach ($directories as $dir) {
            $path = $basePath . '/' . $dir;
            if (!is_dir($path)) {
                $status = "\033[31m[MISSING]\033[0m";
            } else {
                $isWritable = is_writable($path);
                $owner = posix_getpwuid(fileowner($path))['name'] ?? 'unknown';
                $group = posix_getgrgid(filegroup($path))['name'] ?? 'unknown';
                $perms = substr(sprintf('%o', fileperms($path)), -4);
                
                $status = $isWritable ? "\033[32m[OK]\033[0m" : "\033[31m[NOT WRITABLE]\033[0m";
                $status .= " (Owner: $owner:$group, Perms: $perms)";
            }
            $this->line(str_pad($dir, 30) . $status);
        }

        $this->line("");
        $this->info("Step 2: Recommended TODO (Run these as root/sudo)");
        $this->line("--------------------------------------------------");
        
        $this->warn("# Option A: Fast & Simple (Recursive Group Ownership)");
        $this->line("sudo chown -R $currentUser:$webUser " . $basePath . "/storage " . $basePath . "/bootstrap/cache " . $basePath . "/public/uploads");
        $this->line("sudo chmod -R g+w " . $basePath . "/storage " . $basePath . "/bootstrap/cache " . $basePath . "/public/uploads");
        $this->line("sudo find " . $basePath . "/storage -type d -exec chmod g+s {} +");
        
        $this->line("");
        $this->warn("# Option B: Modern Linux (ACLs - Best for Shared Access)");
        $this->line("sudo setfacl -R -m u:$webUser:rwx,u:$currentUser:rwx " . $basePath . "/storage");
        $this->line("sudo setfacl -dR -m u:$webUser:rwx,u:$currentUser:rwx " . $basePath . "/storage");

        $this->line("");
        $this->info("Step 3: Troubleshooting");
        $this->line("If logs are still not showing, check if SELinux is blocking (CentOS/RHEL):");
        $this->line("sudo chcon -Rt httpd_sys_rw_content_t " . $basePath . "/storage");
    }

    protected function detectWebUser(): string
    {
        // Try common linux users
        $users = ['www-data', 'nginx', 'apache', 'http', '_www'];
        foreach ($users as $user) {
            if (function_exists('posix_getpwnam') && posix_getpwnam($user)) {
                return $user;
            }
        }

        // Try to find it via running processes if posix fails
        $output = [];
        @exec("ps aux | grep -E 'nginx|apache|php-fpm' | grep -v root | head -1 | awk '{print $1}'", $output);
        if (!empty($output[0])) {
            return trim($output[0]);
        }

        return 'www-data'; // Fallback
    }
}