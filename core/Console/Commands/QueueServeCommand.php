<?php

/**
 * =============================================================================
 * QueueServeCommand — CLI Command to Start the Queue Server
 * =============================================================================
 *
 * Registered as 'queue:serve' in the console. Starts the QueueServer process.
 *
 * Usage:
 * php console queue:serve                       # defaults from config/queue.php
 * php console queue:serve --port=9091           # custom port
 * php console queue:serve --host=0.0.0.0        # bind to all interfaces
 *
 * @package Framework\Core\Console\Commands
 */

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Queue\QueueServer;

class QueueServeCommand extends Command
{
    protected string $signature = 'queue:serve';
    protected string $description = 'Start the in-memory job queue server';

    public function execute(): void
    {
        $defaultHost = '127.0.0.1';
        $defaultPort = 9090;

        if (function_exists('config')) {
            $queueConfig = config('queue');
            if (is_array($queueConfig)) {
                $defaultHost = $queueConfig['host'] ?? $defaultHost;
                $defaultPort = $queueConfig['port'] ?? $defaultPort;
            }
        }

        $host = $this->option('host', $defaultHost);
        $port = (int) $this->option('port', $defaultPort);
        
        // ── NEW: Capture the UI Port ──
        $uiPortOption = $this->option('ui-port', null);
        $uiPort = $uiPortOption ? (int) $uiPortOption : null;

        if ($this->option('silent') && !$this->option('bg')) {
            $php = PHP_BINARY;
            $command = "{$php} console queue:serve --host={$host} --port={$port} --silent --bg";
            if ($uiPort) $command .= " --ui-port={$uiPort}";
            $command .= " > /dev/null 2>&1 & echo $!";
            $output = [];
            exec($command, $output);
            $pid = trim($output[0] ?? 'unknown');
            echo "Queue Server started in background. PID: {$pid}\n";
            return;
        }

        $this->info("Starting Queue Server on tcp://{$host}:{$port}...");
        if ($uiPort) {
            $this->info("Starting Web UI on http://{$host}:{$uiPort}...");
        }
        $this->line("Press Ctrl+C to stop gracefully\n");

        // Pass the uiPort to the constructor
        $server = new QueueServer($host, $port, $uiPort);
        $server->setSilent((bool)$this->option('silent'));

        $server->setBeforeJobHook(function () {
            // Your DB reconnect logic

            $rootPath = dirname(dirname(dirname(__DIR__)));
            print_r("Root: ". $rootPath);
            require_once $rootPath."/bootstrap/autoload.php";


            // If you have a global app() container, reconnect DB/Redis so the
            // child doesn't share the parent's TCP handles:
            // app('db')->reconnect();
        });

        try {
            $server->start();
        } catch (\Exception $e) {
            $this->error("Failed to start Queue Server: " . $e->getMessage());
        }
    }
}