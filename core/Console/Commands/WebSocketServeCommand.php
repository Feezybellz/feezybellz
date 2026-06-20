<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\WebSocket\WebSocketServer;

class WebSocketServeCommand extends Command
{
    protected string $signature = 'websocket:serve';
    protected string $description = 'Start the WebSocket server';
    
    public function execute(): void
    {
        $host = $this->option('host', config('app.websocket.host', '0.0.0.0'));
        $port = (int)$this->option('port', config('app.websocket.port', 8080));
        $internalPort = (int)$this->option('internal-port', config('app.websocket.internal_port', 8081));

        $sslCert = $this->option('ssl-cert', '');
        $sslKey = $this->option('ssl-key', '');
        $sslPassphrase = $this->option('ssl-passphrase', '');

        if ($this->option('silent') && !$this->option('bg')) {
            $php = PHP_BINARY;
            $command = "{$php} console websocket:serve --host={$host} --port={$port} --internal-port={$internalPort} --silent --bg";
            if ($sslCert) $command .= " --ssl-cert=" . escapeshellarg($sslCert);
            if ($sslKey) $command .= " --ssl-key=" . escapeshellarg($sslKey);
            if ($sslPassphrase) $command .= " --ssl-passphrase=" . escapeshellarg($sslPassphrase);
            $command .= " > /dev/null 2>&1 & echo $!";
            $output = [];
            exec($command, $output);
            $pid = trim($output[0] ?? 'unknown');
            echo "WebSocket server started in background. PID: {$pid}\n";
            return;
        }
        
        $server = new WebSocketServer($host, $port, $internalPort);
        $server->setSilent((bool)$this->option('silent'));

        // Configure SSL if certificate provided
        if ($sslCert !== '') {
            if (!file_exists($sslCert)) {
                $this->error("SSL certificate not found: {$sslCert}");
                return;
            }

            $sslConfig = ['local_cert' => $sslCert];

            if ($sslKey !== '') {
                if (!file_exists($sslKey)) {
                    $this->error("SSL private key not found: {$sslKey}");
                    return;
                }
                $sslConfig['local_pk'] = $sslKey;
            }

            if ($sslPassphrase !== '') {
                $sslConfig['passphrase'] = $sslPassphrase;
            }

            $server->enableSSL($sslConfig);
        }

        $scheme = $server->isSSL() ? 'wss' : 'ws';
        $this->info("Starting WebSocket server on {$scheme}://{$host}:{$port}...");

        if ($server->isSSL()) {
            $this->success("SSL/TLS enabled — secure WebSocket (wss://)");
        }

        $this->line("Press Ctrl+C to stop");
        $this->line("");

        // 🚀 CRITICAL: Load the WebSocket routes
        $routeFile = base_path('routes/websocket.php');
        if (file_exists($routeFile)) {
            require $routeFile;
        }
        
        // Register event handlers
        $this->registerEventHandlers($server);
        
        // 🌍 Register global router events (disconnect, connection)
        \Framework\Core\Routing\WSRouter::attachServer($server);
        
        // Handle Ctrl+C gracefully
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function() use ($server) {
                $this->line("");
                $this->info("Shutting down WebSocket server...");
                $server->stop();
                exit(0);
            });
        }
        
        try {
            $server->start();
        } catch (\Exception $e) {
            $this->error("Failed to start WebSocket server: " . $e->getMessage());
        }
    }
    
    /**
     * Register default event handlers
     */
    private function registerEventHandlers(WebSocketServer $server): void
    {
        // ── Global Logger ───────────────────────────────────────────
        $server->on('*', function($data, $socket) {
            $event = $data['event'] ?? 'unknown';
            $id = property_exists($socket, 'id') ? $socket->id : 'system';
            $this->line("Event: {$event} from {$id}");
        });

        // ── Connection event ─────────────────────────────────────────
        // The 2nd arg is a ClientSocket — emit directly to the new client.
        // $server->on('connection', function($data, $socket) {
        //     $this->success("New client connected: {$socket->id}");
            
        //     // Send welcome message
        //     $socket->emit('welcome', [
        //         'message' => 'Connected to WebSocket server',
        //         'clientId' => $socket->id,
        //         'timestamp' => time()
        //     ]);

        //     // ── Per-socket handlers (Socket.IO style) ────────────────
        //     // These handlers only fire for THIS specific client.

        //     // socket-echo: echoes back via per-socket handler (tests $socket->on)
        //     $socket->on('socket-echo', function ($data, $socket, $reply) {
        //         $socket->emit('socket-echo-reply', [
        //             'echo' => $data,
        //             'from' => $socket->id,
        //         ]);
        //         if ($reply) {
        //             $reply($data);
        //         }
        //     });

        //     // socket-info: returns socket details (tests per-socket ack)
        //     $socket->on('socket-info', function ($data, $socket, $reply) {
        //         if ($reply) {
        //             $reply([
        //                 'id' => $socket->id,
        //                 'rooms' => $socket->getRooms(),
        //             ]);
        //         }
        //     });
        // });

        // Inside registerEventHandlers() in WebSocketServeCommand.php
$server->on('connection', function($data, $socket) {
    // Dynamically attach all events defined in your routes file
    \Framework\Core\Routing\WSRouter::attach($socket);
    
    $socket->emit('welcome', ['id' => $socket->id]);
});
        
        // ── Disconnect event ─────────────────────────────────────────
        $server->on('disconnect', function($data, $socket) {
            $this->warn("Client disconnected: {$socket->id}");
        });
        
        // ── Join room ────────────────────────────────────────────────
        $server->on('join', function($data, $socket) {
            $room = $data['data']['room'] ?? null;
            
            if ($room) {
                $socket->join($room);
                $this->info("Client {$socket->id} joined room: {$room}");
                
                // Notify others in the room
                $socket->to($room)->emit('user_joined', [
                    'clientId' => $socket->id,
                    'room' => $room
                ]);
            }
        });
        
        // ── Leave room ───────────────────────────────────────────────
        $server->on('leave', function($data, $socket) {
            $room = $data['data']['room'] ?? null;
            
            if ($room) {
                $socket->leave($room);
                $this->info("Client {$socket->id} left room: {$room}");
                
                // Notify others in the room
                $socket->to($room)->emit('user_left', [
                    'clientId' => $socket->id,
                    'room' => $room
                ]);
            }
        });
        
        // ── Message event ────────────────────────────────────────────
        $server->on('message', function($data, $socket, $reply) {
            $message = $data['data'];
            
            $this->line("Message from {$socket->id}: " . json_encode($message));
            
            if ($reply) {
                $reply([
                    'status' => 'delivered',
                    'timestamp' => time(),
                ]);
            }

            // Broadcast to everyone except sender
            $socket->broadcast('message', [
                'from' => $socket->id,
                'message' => $message,
                'timestamp' => time()
            ]);
        });
        
        // ── Room message event ───────────────────────────────────────
        $server->on('room_message', function($data, $socket) {
            $room = $data['data']['room'] ?? null;
            $message = $data['data']['message'] ?? null;
            
            if ($room && $message) {
                $this->line("Room message from {$socket->id} in {$room}: {$message}");
                
                $socket->to($room)->emit('room_message', [
                    'from' => $socket->id,
                    'room' => $room,
                    'message' => $message,
                    'timestamp' => time()
                ]);
            }
        });

        // ── Ping event (demonstrates ack/reply) ─────────────────────
        $server->on('ping', function($data, $socket, $reply) {
            $this->line("Ping from {$socket->id}");

            if ($reply) {
                $reply([
                    'pong' => true,
                    'serverTime' => time(),
                ]);
            }
        });

        // ── Server-time event (demonstrates emitWithAck) ────────────
        $server->on('server-time', function($data, $socket, $reply) {
            if ($reply) {
                $reply([
                    'time' => date('Y-m-d H:i:s'),
                    'timestamp' => microtime(true),
                ]);
            }
        });

        // ── Echo event (useful for testing) ──────────────────────────
        $server->on('echo', function($data, $socket, $reply) {
            if ($reply) {
                $reply($data['data']);
            }
        });

        // ── Internal Server Triggers (From Sync PHP) ─────────────────
        $server->on('broadcast', function($payload) use ($server) {
            $event = $payload['data']['event'] ?? 'message';
            $data = $payload['data']['payload'] ?? [];
            
            foreach ($server->getClients() as $client) {
                $client->emit($event, $data);
            }
        });

        $server->on('room_broadcast', function($payload) use ($server) {
            $room = $payload['data']['room'] ?? null;
            $event = $payload['data']['event'] ?? 'message';
            $data = $payload['data']['payload'] ?? [];
            
            if ($room && $server->hasRoom($room)) {
                $server->getRoom($room)->broadcast($event, $data);
            }
        });
    }
}
