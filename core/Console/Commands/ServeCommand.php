<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class ServeCommand extends Command
{
    public function execute(): void
    {
        $host = $this->option('host', '127.0.0.1');
        $port = $this->option('port', '8000');
        
        $publicPath = dirname(dirname(dirname(__DIR__))) . '/public';
        
        if (!is_dir($publicPath)) {
            $this->error('Public directory not found.');
            return;
        }
        
        $this->info("Starting development server on http://{$host}:{$port}");
        $this->info("Press Ctrl+C to stop the server.\n");
        
        $command = sprintf(
            'php -S %s:%s -t %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($publicPath)
        );

        if ($this->option('silent')) {
            $command .= ' > /dev/null 2>&1 & echo $!';
            $output = [];
            exec($command, $output);
            $pid = trim($output[0] ?? 'unknown');
            echo "Development server started in background on http://{$host}:{$port}. PID: {$pid}\n";
            return;
        }
        
        passthru($command);
    }
}
