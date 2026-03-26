<?php

/**
 * =============================================================================
 * QueueDashboardCommand — CLI Live UI for the Queue Server
 * =============================================================================
 *
 * Registered as 'queue:ui' in the console. Displays a live-updating terminal
 * dashboard of the running in-memory queue's statistics.
 *
 * Usage:
 * php console queue:ui
 * php console queue:ui --host=127.0.0.1 --port=9090
 *
 * @package Framework\Core\Console\Commands
 */

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Queue\QueueClient;

class QueueDashboardCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'queue:ui';

    /**
     * A short description shown in the 'help' output.
     *
     * @var string
     */
    protected $description = 'Show a live terminal dashboard for the queue server';

    /**
     * Execute the command — run the live dashboard loop.
     *
     * @return void
     */
    public function execute(): void
    {
        // ── Step 1: Load Configuration ──────────────────────────────────
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

        // ── Step 2: Terminal UI Setup (ANSI Magic) ──────────────────────
        $clearScreen = "\033[2J\033[H"; // Clears screen and moves cursor to top-left
        $colorGreen  = "\033[32m";
        $colorRed    = "\033[31m";
        $colorYellow = "\033[33m";
        $colorReset  = "\033[0m";
        $colorCyan   = "\033[36m";

        // Hide the blinking terminal cursor for a smooth UI experience
        echo "\033[?25l";

        // ── Step 3: Graceful Exit Handling ──────────────────────────────
        // If the user hits Ctrl+C, we MUST restore the blinking cursor,
        // otherwise their terminal will be permanently cursor-less until
        // they restart their shell!
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }
        
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () {
                echo "\033[?25h\n"; // Restore cursor
                echo "\033[36mExiting dashboard...\033[0m\n";
                exit(0);
            });
        }

        // ── Step 4: The Live Render Loop ────────────────────────────────
        while (true) {
            // Fetch stats from the server (1-second timeout so UI doesn't hang)
            $client = new QueueClient($host, $port, 1);
            $response = $client->getStats();

            // Execute the screen clear right before printing to prevent flicker
            echo $clearScreen;

            echo $colorCyan . "====================================================\n";
            echo "             IN-MEMORY QUEUE DASHBOARD              \n";
            echo "====================================================\n" . $colorReset;

            echo "Time: " . date('Y-m-d H:i:s') . "\n";
            echo "Host: tcp://{$host}:{$port}\n\n";

            // ── Render Offline State ──
            if (!$response['success']) {
                echo $colorRed . "STATUS: OFFLINE OR UNREACHABLE\n" . $colorReset;
                echo "Error: " . $response['message'] . "\n\n";
                echo "Waiting for server to start... (Retrying in 1s)\n";
            } 
            // ── Render Online State ──
            else {
                $stats = $response['data'];

                echo $colorGreen . "STATUS: ONLINE\n\n" . $colorReset;

                // Live State
                echo $colorYellow . "--- Live State ---\n" . $colorReset;
                echo str_pad("Active Workers (Forked):", 25) . $stats['active_children'] . "\n";
                echo str_pad("Queue Depth (Waiting):", 25) . $stats['queue_depth'] . "\n";
                echo str_pad("Incoming Connections:", 25)  . $stats['connections'] . "\n\n";

                // Lifetime Statistics
                echo $colorYellow . "--- Lifetime Stats ---\n" . $colorReset;
                echo str_pad("Total Received:", 25) . $stats['received'] . "\n";
                echo str_pad("Total Executed:", 25) . $colorGreen . $stats['executed'] . $colorReset . "\n";
                
                // Colorize failures red if > 0
                $failedStr = $stats['failed'] > 0 ? $colorRed . $stats['failed'] . $colorReset : "0";
                echo str_pad("Total Failed:", 25)   . $failedStr . "\n\n";

                // Up Next (Optional: If you passed 'next_jobs' from the server)
                if (isset($stats['next_jobs']) && !empty($stats['next_jobs'])) {
                    echo $colorYellow . "--- Up Next (Top 5) ---\n" . $colorReset;
                    $count = 0;
                    foreach ($stats['next_jobs'] as $job) {
                        if ($count++ >= 5) break; 
                        
                        $callable = is_array($job['callable']) 
                            ? implode('::', $job['callable']) 
                            : $job['callable'];
                            
                        // Truncate long class names for the UI
                        if (strlen($callable) > 35) {
                            $callable = substr($callable, 0, 32) . '...';
                        }
                        
                        echo " - [{$job['id']}] {$callable}\n";
                    }
                    echo "\n";
                }
            }

            echo "====================================================\n";
            echo "Press Ctrl+C to exit dashboard.\n";

            // Wait 1 second before the next redraw
            sleep(1);
        }
    }
}
