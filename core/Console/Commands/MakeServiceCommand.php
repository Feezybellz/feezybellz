<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class MakeServiceCommand extends Command
{
    protected string $signature = 'make:service';
    protected string $description = 'Generate systemd service files for WebSocket and Queue services';

    public function execute(): void
    {
        $type = $this->argument(0);
        
        if (!$type) {
            $this->info("Usage: php console make:service [websocket|queue|all]");
            $this->error("Please specify a service type.");
            return;
        }

        $projectPath = realpath(dirname(dirname(dirname(__DIR__))));
        $servicesPath = $projectPath . '/storage/services';

        if (!is_dir($servicesPath)) {
            mkdir($servicesPath, 0755, true);
        }

        $user = get_current_user();
        $group = posix_getgrgid(posix_getgid())['name'] ?? $user;
        $phpPath = PHP_BINARY;

        if ($type === 'websocket' || $type === 'all') {
            $this->generateWebsocketService($projectPath, $servicesPath, $user, $group, $phpPath);
        }

        if ($type === 'queue' || $type === 'all') {
            $this->generateQueueService($projectPath, $servicesPath, $user, $group, $phpPath);
        }
    }

    protected function generateWebsocketService(string $projectPath, string $servicesPath, string $user, string $group, string $php): void
    {
        $name = basename($projectPath) . '-websocket';
        $content = <<<EOL
[Unit]
Description=WebSocket Server for Locanse
After=network.target

[Service]
Type=simple
User={$user}
Group={$group}
WorkingDirectory={$projectPath}
ExecStart={$php} console websocket:serve
Restart=always
RestartSec=5
StandardOutput=append:{$projectPath}/storage/logs/websocket.log
StandardError=append:{$projectPath}/storage/logs/websocket.error.log

[Install]
WantedBy=multi-user.target
EOL;

        $fileName = "{$name}.service";
        $filePath = "{$servicesPath}/{$fileName}";
        file_put_contents($filePath, $content);
        
        $this->success("Systemd service generated: storage/services/{$fileName}");
        $this->info("To install:");
        $this->line("1. sudo cp {$filePath} /etc/systemd/system/");
        $this->line("2. sudo systemctl daemon-reload");
        $this->line("3. sudo systemctl enable {$name}");
        $this->line("4. sudo systemctl start {$name}");
        $this->line("");
    }

    protected function generateQueueService(string $projectPath, string $servicesPath, string $user, string $group, string $php): void
    {
        $name = basename($projectPath) . '-queue';
        $content = <<<EOL
[Unit]
Description=Queue Server for Locanse
After=network.target

[Service]
Type=simple
User={$user}
Group={$group}
WorkingDirectory={$projectPath}
ExecStart={$php} console queue:serve
Restart=always
RestartSec=5
StandardOutput=append:{$projectPath}/storage/logs/queue.log
StandardError=append:{$projectPath}/storage/logs/queue.error.log

[Install]
WantedBy=multi-user.target
EOL;

        $fileName = "{$name}.service";
        $filePath = "{$servicesPath}/{$fileName}";
        file_put_contents($filePath, $content);

        $this->success("Systemd service generated: storage/services/{$fileName}");
        $this->info("To install:");
        $this->line("1. sudo cp {$filePath} /etc/systemd/system/");
        $this->line("2. sudo systemctl daemon-reload");
        $this->line("3. sudo systemctl enable {$name}");
        $this->line("4. sudo systemctl start {$name}");
        // restart 
        $this->line("5. sudo systemctl restart {$name}");
        $this->line("");
    }
}
