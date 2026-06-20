<?php

namespace Framework\Core\Support;

class SystemSetup
{
    /**
     * Generate dynamic, OS-aware installation instructions for missing extensions and servers.
     */
    public static function getExtensionInstallMessage(string $extension, string $serviceName, int $defaultPort): string
    {
        $phpDriverCmd = '';
        $serverCmd = '';
        
        $os = PHP_OS_FAMILY;

        if ($os === 'Darwin') {
            // macOS / Homebrew
            $phpDriverCmd = "pecl install {$extension}";
            $serverCmd = "brew install {$serviceName} && brew services start {$serviceName}";
        } elseif ($os === 'Windows') {
            // Windows
            $phpDriverCmd = "Enable 'extension={$extension}' in your php.ini or install via PECL";
            $serverCmd = "Use Docker Desktop or download the native Windows binaries.";
        } else {
            // Linux (Detect Package Manager)
            if (is_executable('/usr/bin/pacman')) {
                // Arch Linux
                $phpDriverCmd = "sudo pacman -S php-{$extension}";
                $serverCmd = "sudo pacman -S {$serviceName} && sudo systemctl enable --now {$serviceName}";
            } elseif (is_executable('/usr/bin/apt') || is_executable('/usr/bin/apt-get')) {
                // Debian / Ubuntu
                $phpDriverCmd = "sudo apt-get install php-{$extension}";
                $serverCmd = "sudo apt-get install {$serviceName} && sudo systemctl enable --now {$serviceName}";
            } elseif (is_executable('/usr/bin/dnf') || is_executable('/usr/bin/yum')) {
                // RHEL / Fedora / CentOS
                $pkgManager = is_executable('/usr/bin/dnf') ? 'dnf' : 'yum';
                $phpDriverCmd = "sudo {$pkgManager} install php-pecl-{$extension}";
                $serverCmd = "sudo {$pkgManager} install {$serviceName} && sudo systemctl enable --now {$serviceName}";
            } elseif (is_executable('/sbin/apk') || is_executable('/usr/sbin/apk')) {
                // Alpine Linux
                $phpDriverCmd = "apk add php82-pecl-{$extension}";
                $serverCmd = "apk add {$serviceName} && rc-update add {$serviceName} default && rc-service {$serviceName} start";
            } else {
                // Fallback
                $phpDriverCmd = "pecl install {$extension}";
                $serverCmd = "Use your system's package manager to install {$serviceName}";
            }
        }

        $dockerCmd = "docker run -d --name {$serviceName}-server -p {$defaultPort}:{$defaultPort} {$serviceName}";

        return "\nThe '{$extension}' extension is missing.\n" .
               "----------------------------------------------------\n" .
               "[1] Install the PHP Driver:\n" .
               "    > {$phpDriverCmd}\n\n" .
               "[2] Install the {$serviceName} Server (if you haven't already):\n" .
               "    > Native: {$serverCmd}\n" .
               "    > Docker: {$dockerCmd}\n" .
               "----------------------------------------------------\n" .
               "Don't forget to restart your web server or PHP-FPM process after installing!";
    }
}
