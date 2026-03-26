<?php

namespace Framework\Core\WebSocket;

class WebSocketServer
{
    private $socket;
    private $clients = [];
    private $rooms = [];
    private $eventHandlers = [];
    private $clientEventHandlers = [];
    private $socketToClientMap = [];
    private $host;
    private $port;
    private $running = false;
    private $pingInterval = 30;
    private $pingTimeout = 10;
    private $lastPingTime = 0;
    private $allowedOrigins = [];
    private $sslConfig = [];
    private $silent = false;
    private $internalSocket;
    private $internalPort;

    public function __construct(string $host = '0.0.0.0', int $port = 8080, int $internalPort = 8081)
    {
        $this->host = $host;
        $this->port = $port;
        $this->internalPort = $internalPort;
        $this->lastPingTime = microtime(true);
    }

    /**
     * Set allowed origins for CSWSH protection.
     * Empty array = allow all (development only).
     */
    public function setAllowedOrigins(array $origins): void
    {
        $this->allowedOrigins = $origins;
    }

    /**
     * Configure heartbeat ping/pong timing.
     */
    public function setPingConfig(int $interval = 30, int $timeout = 10): void
    {
        $this->pingInterval = $interval;
        $this->pingTimeout = $timeout;
    }

    /**
     * Enable SSL/TLS for wss:// connections.
     *
     *   $server->enableSSL([
     *       'local_cert'  => '/etc/ssl/certs/server.pem',
     *       'local_pk'    => '/etc/ssl/private/server.key',
     *       'passphrase'  => 'optional-passphrase',
     *   ]);
     *
     * For Let's Encrypt:
     *   $server->enableSSL([
     *       'local_cert' => '/etc/letsencrypt/live/example.com/fullchain.pem',
     *       'local_pk'   => '/etc/letsencrypt/live/example.com/privkey.pem',
     *   ]);
     *
     * @param array $config SSL context options (see https://www.php.net/manual/en/context.ssl.php)
     */
    public function enableSSL(array $config): void
    {
        if (!isset($config['local_cert'])) {
            throw new \InvalidArgumentException("SSL config requires 'local_cert' (path to certificate file)");
        }

        $this->sslConfig = $config;
    }

    /**
     * Check if SSL is enabled.
     */
    public function isSSL(): bool
    {
        return !empty($this->sslConfig);
    }

    /**
     * Start the WebSocket server.
     */
    public function start(): void
    {
        $protocol = $this->isSSL() ? 'ssl' : 'tcp';
        $address = "{$protocol}://{$this->host}:{$this->port}";
        $internalAddress = "tcp://127.0.0.1:{$this->internalPort}";

        $contextOptions = [
            'socket' => ['so_reuseaddr' => true],
        ];

        if ($this->isSSL()) {
            $contextOptions['ssl'] = array_merge([
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ], $this->sslConfig);
        }

        $context = \stream_context_create($contextOptions);

        $this->socket = \stream_socket_server($address, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        if (!$this->socket) {
            throw new \RuntimeException("Failed to create socket: {$errstr} ({$errno})");
        }

        $this->internalSocket = \stream_socket_server($internalAddress, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        if (!$this->internalSocket) {
            $this->log("⚠️ Failed to create internal socket: {$errstr} ({$errno})");
        } else {
            \stream_set_blocking($this->internalSocket, false);
            $this->log("Internal trigger server started on 127.0.0.1:{$this->internalPort}");
        }

        \stream_set_blocking($this->socket, false);

        $this->running = true;
        $scheme = $this->isSSL() ? 'wss' : 'ws';
        $this->log("WebSocket server started on {$scheme}://{$this->host}:{$this->port}");

        $this->loop();
    }

    // ─── Event Loop ──────────────────────────────────────────────────

    /**
     * Main event loop with heartbeat checks.
     */
    private function loop(): void
    {
        while ($this->running) {
            $read = array_merge([$this->socket], array_column($this->clients, 'socket'));
            if ($this->internalSocket) {
                $read[] = $this->internalSocket;
            }
            $write = null;
            $except = null;

            $result = \stream_select($read, $write, $except, 0, 200000);

            if ($result === false) {
                $err = error_get_last();
                $this->log("stream_select error: " . ($err['message'] ?? 'unknown'));
                break;
            }

            // Accept new internal connection
            if ($this->internalSocket && in_array($this->internalSocket, $read)) {
                $newInternal = @\stream_socket_accept($this->internalSocket, 0);
                if ($newInternal !== false) {
                    $data = \fread($newInternal, 65535);
                    $this->handleInternalTrigger($data);
                    \fclose($newInternal);
                }
                unset($read[array_search($this->internalSocket, $read)]);
            }

            // Accept new connections
            if (in_array($this->socket, $read)) {
                $newSocket = \stream_socket_accept($this->socket, 0);
                if ($newSocket !== false) {
                    // For SSL: enable crypto on the accepted socket
                    if ($this->isSSL()) {
                        \stream_set_blocking($newSocket, true);
                        $crypto = @\stream_socket_enable_crypto(
                            $newSocket,
                            true,
                            STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER
                        );
                        if ($crypto !== true) {
                            $this->log("SSL handshake failed for incoming connection");
                            if (is_resource($newSocket)) {
                                \fclose($newSocket);
                            }
                            unset($read[array_search($this->socket, $read)]);
                            continue;
                        }
                    }
                    \stream_set_blocking($newSocket, false);
                    $this->handleNewConnection($newSocket);
                }
                unset($read[array_search($this->socket, $read)]);
            }

            // Read from existing clients
            foreach ($read as $clientSocket) {
                $clientId = $this->getClientIdBySocket($clientSocket);
                if ($clientId === null) {
                    continue;
                }

                $data = \fread($clientSocket, 65535);

                if ($data === false || $data === '') {
                    if (\feof($clientSocket)) {
                        $this->disconnect($clientId);
                    }
                } else {
                    $this->handleClientData($clientId, $data);
                }
            }

            // Heartbeat: ping idle clients, disconnect zombies
            $this->checkHeartbeat();
        }
    }

    // ─── Connection Handling ─────────────────────────────────────────

    /**
     * Register a new TCP connection (pre-handshake).
     */
    private function handleNewConnection($socket): void
    {
        $clientId = uniqid('client_', true);
        $resourceId = (int) $socket;

        $this->clients[$clientId] = [
            'socket'        => $socket,
            'handshake'     => false,
            'rooms'         => [],
            'buffer'        => '',
            'lastPong'      => microtime(true),
            'pendingFrames' => [],
            'customData'    => [], // Persistent storage bucket for $socket->property access
        ];

        $this->socketToClientMap[$resourceId] = $clientId;

        // $this->log("New connection: {$clientId}");
    }

    /**
     * Buffer incoming bytes then process complete frames.
     */
    private function handleClientData(string $clientId, string $data): void
    {
        if (!isset($this->clients[$clientId])) {
            return;
        }

        $client = &$this->clients[$clientId];

        // Pre-handshake: the first message is always an HTTP upgrade request.
        if (!$client['handshake']) {
            $client['buffer'] .= $data;
            
            // 🔒 SECURITY CHECK: Detect TLS handshake on a non-SSL port
            if (!$this->isSSL() && strlen($client['buffer']) >= 3) {
                $firstByte = ord($client['buffer'][0]);
                if ($firstByte === 0x16) {
                    $this->log("⚠️  PROTOCOL ERROR: Received encrypted (WSS) data on a non-SSL port.");
                    $this->log("Your browser is trying to connect securely, but the server is in unsecure mode.");
                    $this->log("Please provide SSL certificates or use a reverse proxy (Nginx/Cloudflare).");
                    $this->disconnect($clientId);
                    return;
                }
            }

            // $this->log("Handshake buffer size: " . strlen($client['buffer']) . " bytes");
            
            // Check if we have the full HTTP header (ended by \r\n\r\n)
            if (strpos($client['buffer'], "\r\n\r\n") !== false) {
                $headerEndPos = strpos($client['buffer'], "\r\n\r\n") + 4;
                $handshakeData = substr($client['buffer'], 0, $headerEndPos);
                $remainingData = substr($client['buffer'], $headerEndPos);
                
                $client['buffer'] = $remainingData;
                $this->performHandshake($clientId, $handshakeData);
            } else {
                // If the buffer is getting large without a handshake, log the start of it to see if it's garbage (SSL?)
                if (strlen($client['buffer']) > 1000) {
                    $this->log("Warning: Handshake buffer exceeded 1000 bytes without CRLF. Data start: " . bin2hex(substr($client['buffer'], 0, 16)));
                }
            }
            return;
        }

        // Append to per-client buffer
        $client['buffer'] .= $data;

        // Drain as many complete frames as possible
        while (strlen($client['buffer']) >= 2) {
            try {
                $frame = WebSocketFrame::decode($client['buffer']);
            } catch (\RuntimeException $e) {
                $this->log("Frame error from {$clientId}: " . $e->getMessage());
                $this->disconnect($clientId);
                return;
            }

            if ($frame['complete'] === false) {
                break; // wait for more data
            }

            // Advance the buffer past the consumed bytes
            $client['buffer'] = substr($client['buffer'], $frame['bytesRead']);

            switch ($frame['opcode']) {
                case 0x8: // Close
                    $this->sendFrame($clientId, '', 0x8);
                    $this->disconnect($clientId);
                    return;

                case 0x9: // Ping → Pong
                    $this->sendFrame($clientId, $frame['payload'], 0xA);
                    continue 2;

                case 0xA: // Pong
                    $client['lastPong'] = microtime(true);
                    continue 2;

                case 0x0: // Continuation
                case 0x1: // Text
                case 0x2: // Binary
                    $this->handleDataFrame($clientId, $frame);
                    break;
            }
        }
    }

    /**
     * Reassemble fragmented frames, then dispatch the event.
     */
    private function handleDataFrame(string $clientId, array $frame): void
    {
        if (!isset($this->clients[$clientId])) {
            return;
        }

        $client = &$this->clients[$clientId];

        if ($frame['fin'] === 0) {
            // Non-final fragment — accumulate
            $client['pendingFrames'][] = $frame['payload'];
            return;
        }

        // Final fragment (or unfragmented message)
        $payload = $frame['payload'];
        if (!empty($client['pendingFrames'])) {
            $payload = implode('', $client['pendingFrames']) . $payload;
            $client['pendingFrames'] = [];
        }

        // $this->log("Raw Payload from {$clientId}: " . substr($payload, 0, 100) . (strlen($payload) > 100 ? '...' : ''));

        $message = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("JSON Decode Error for {$clientId}: " . json_last_error_msg());
            return;
        }

        if (is_array($message) && isset($message['event'])) {
            $ackId = $message['_ackId'] ?? null;
            $this->handleEvent($clientId, $message['event'], $message['data'] ?? null, $ackId);
        } else {
            $this->log("Warning: Received invalid message structure from {$clientId} (missing 'event' key)");
        }
    }

    // ─── Handshake ───────────────────────────────────────────────────

    /**
     * Validate and complete the WebSocket upgrade handshake.
     */
    private function performHandshake(string $clientId, string $data): void
    {
        $headers = $this->parseHeaders($data);

        // Validate required WebSocket headers
        if (
            !isset($headers['Sec-WebSocket-Key'])
            || !isset($headers['Upgrade'])
            || !isset($headers['Connection'])
        ) {
            $this->log("Invalid handshake headers from {$clientId}");
            $this->disconnect($clientId);
            return;
        }

        if (
            stripos($headers['Upgrade'], 'websocket') === false
            || stripos($headers['Connection'], 'upgrade') === false
        ) {
            $this->log("Invalid upgrade request from {$clientId}");
            $this->disconnect($clientId);
            return;
        }

        // CSWSH protection: reject unknown origins when whitelist is set
        if (!empty($this->allowedOrigins)) {
            $origin = $headers['Origin'] ?? ($headers['Sec-WebSocket-Origin'] ?? '');
            if (!in_array($origin, $this->allowedOrigins, true)) {
                $this->log("Rejected origin '{$origin}' from {$clientId}");
                $this->disconnect($clientId);
                return;
            }
        }

        $key = $headers['Sec-WebSocket-Key'];
        $acceptKey = base64_encode(
            sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)
        );

        $response  = "HTTP/1.1 101 Switching Protocols\r\n";
        $response .= "Upgrade: websocket\r\n";
        $response .= "Connection: Upgrade\r\n";
        $response .= "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";

        $written = \fwrite($this->clients[$clientId]['socket'], $response);
        if ($written === false) {
            $this->log("Failed to send handshake to {$clientId}");
            $this->disconnect($clientId);
            return;
        }

        $this->clients[$clientId]['handshake'] = true;

        // $this->log("Handshake completed: {$clientId}");
        $this->emit('connection', ['clientId' => $clientId]);
    }

    /**
     * Parse raw HTTP header block into key => value array.
     */
    private function parseHeaders(string $data): array
    {
        $headers = [];
        $lines = explode("\r\n", $data);

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return $headers;
    }

    // ─── Sending ─────────────────────────────────────────────────────

    /**
     * Send a named event + data payload to a single client.
     */
    public function send(string $clientId, string $event, $data = null): bool
    {
        if (!isset($this->clients[$clientId])) {
            return false;
        }

        // 🕒 Timezone Management: Convert all date strings in the payload to ISO 8601 UTC
        $data = recursive_format_dates($data);

        // $this->log("Sending event '{$event}' to client: {$clientId}");
        $message = json_encode(['event' => $event, 'data' => $data]);
        return $this->sendFrame($clientId, $message, 0x1);
    }

    /**
     * Write an encoded WebSocket frame to a client socket.
     */
    private function sendFrame(string $clientId, string $payload, int $opcode = 0x1): bool
    {
        if (!isset($this->clients[$clientId])) {
            return false;
        }

        $frame = WebSocketFrame::encode($payload, $opcode);
        $result = \fwrite($this->clients[$clientId]['socket'], $frame);

        return $result !== false;
    }

    /**
     * Send an event to every connected client.
     */
    public function broadcast(string $event, $data = null, $excludeClientId = null): void
    {
        // $this->log("Broadcasting event '{$event}' to all clients" . ($excludeClientId ? " (excluding {$excludeClientId})" : ""));
        foreach (array_keys($this->clients) as $clientId) {
            if ($clientId !== $excludeClientId) {
                $this->send($clientId, $event, $data);
            }
        }
    }

    // ─── Rooms ───────────────────────────────────────────────────────

    /**
     * Add a client to a room (duplicate-safe).
     */
    public function join(string $clientId, string $room): void
    {
        if (!isset($this->clients[$clientId])) {
            return;
        }

        // Already in this room — no-op
        if (in_array($room, $this->clients[$clientId]['rooms'], true)) {
            return;
        }

        if (!isset($this->rooms[$room])) {
            $this->rooms[$room] = [];
        }

        $this->rooms[$room][] = $clientId;
        $this->clients[$clientId]['rooms'][] = $room;
        // $this->log("Client {$clientId} joined room: {$room}");
    }

    /**
     * Remove a client from a room.
     */
    public function leave(string $clientId, string $room): void
    {
        if (isset($this->rooms[$room])) {
            $this->rooms[$room] = array_values(
                array_filter($this->rooms[$room], function($id) use ($clientId) { return $id !== $clientId; })
            );

            if (empty($this->rooms[$room])) {
                unset($this->rooms[$room]);
            }
        }

        if (isset($this->clients[$clientId])) {
            $this->clients[$clientId]['rooms'] = array_values(
                array_filter($this->clients[$clientId]['rooms'], function($r) use ($room) { return $r !== $room; })
            );
        }

        // $this->log("Client {$clientId} left room: {$room}");
    }

    /**
     * Send an event to every client in a room.
     */
    public function toRoom(string $room, string $event, $data = null, $excludeClientId = null): void
    {
        if (!isset($this->rooms[$room])) {
            return;
        }

        // $this->log("Emitting event '{$event}' to room: {$room}" . ($excludeClientId ? " (excluding {$excludeClientId})" : ""));
        foreach ($this->rooms[$room] as $clientId) {
            if ($clientId !== $excludeClientId) {
                $this->send($clientId, $event, $data);
            }
        }
    }

    /**
     * Get all client IDs currently in a room.
     */
    public function getClientsInRoom(string $room): array
    {
        return $this->rooms[$room] ?? [];
    }

    /**
     * Get all rooms a client has joined.
     */
    public function getClientRooms(string $clientId): array
    {
        return $this->clients[$clientId]['rooms'] ?? [];
    }

    // ─── Events ──────────────────────────────────────────────────────

    /**
     * Register a handler for a named event.
     */
    public function on(string $event, callable $handler): void
    {
        if (!isset($this->eventHandlers[$event])) {
            $this->eventHandlers[$event] = [];
        }

        $this->eventHandlers[$event][] = $handler;
    }

    /**
     * Register a handler for events from a SPECIFIC client (per-socket handler).
     *
     * This is used internally by ClientSocket::on() to support the
     * Socket.IO-style pattern of nesting on() inside a connection handler.
     *
     * Per-socket handlers receive: ($data, $socket, $reply)
     *   - $data  — the raw event payload (unwrapped)
     *   - $socket — the ClientSocket instance
     *   - $reply — callable for ack, or null
     */
    public function onClient(string $clientId, string $event, callable $handler): void
    {
        if (!isset($this->clientEventHandlers[$clientId])) {
            $this->clientEventHandlers[$clientId] = [];
        }

        if (!isset($this->clientEventHandlers[$clientId][$event])) {
            $this->clientEventHandlers[$clientId][$event] = [];
        }

        $this->clientEventHandlers[$clientId][$event][] = $handler;
    }

    /**
     * Dispatch an event to all registered handlers.
     *
     * Handlers receive: ($data, $socket, $reply)
     *   - $socket is a ClientSocket representing the sender
     *   - $reply is a callable when the client expects an acknowledgment,
     *     or null for fire-and-forget events.
     */
    private function emit(string $event, array $data = [], $reply = null): void
    {
        // Build a ClientSocket for the sender (if clientId is present)
        $socket = null;
        if (isset($data['clientId'])) {
            $socket = new ClientSocket($this, $data['clientId']);
        }

        // 1. Fire per-client handlers (Socket.IO style: $socket->on(...))
        if ($socket !== null) {
            $clientId = $data['clientId'];
            if (isset($this->clientEventHandlers[$clientId][$event])) {
                $payload = $data['data'] ?? null;
                
                // Ensure _ackId is available in the payload for per-client handlers
                if (is_array($payload) && isset($data['_ackId']) && !isset($payload['_ackId'])) {
                    $payload['_ackId'] = $data['_ackId'];
                }

                foreach ($this->clientEventHandlers[$clientId][$event] as $handler) {
                    call_user_func($handler, $payload, $socket, $reply);
                }
            }
        }

        // 2. Fire global handlers ($server->on(...))
        if (isset($this->eventHandlers[$event])) {
            foreach ($this->eventHandlers[$event] as $handler) {
                call_user_func($handler, $data, $socket ?? $this, $reply);
            }
        }

        // 3. Fire wildcard handlers ($server->on('*', ...))
        if ($event !== '*' && isset($this->eventHandlers['*'])) {
            $wildcardData = $data;
            $wildcardData['event'] = $event; // Pass the actual event name
            foreach ($this->eventHandlers['*'] as $handler) {
                call_user_func($handler, $wildcardData, $socket ?? $this, $reply);
            }
        }
    }

    // In core/WebSocket/WebSocketServer.php

    /**
     * Route an incoming client message to the event system.
     */
    private function handleEvent(string $clientId, string $event, $data, $ackId = null): void
    {
        $eventData = [
            'clientId' => $clientId,
            'data'     => $data,
            '_ackId'   => $ackId // Keep ackId in the payload for WSRouter/Controllers
        ];

        $reply = $this->createReplyCallback($clientId, $ackId);

        $this->emit($event, $eventData, $reply);
    }

    /**
     * Creates a one-shot callback for client acknowledgments.
     */
    private function createReplyCallback(string $clientId, $ackId)
    {
        if ($ackId === null) {
            return null;
        }

        $replied = false;
        return function ($responseData = null) use ($clientId, $ackId, &$replied) {
            if ($replied) return;
            $replied = true;
            $this->send($clientId, '__ack__', [
                '_ackId' => $ackId,
                'data'   => $responseData,
            ]);
        };
    }
    // ─── Connection Lifecycle ────────────────────────────────────────

    /**
     * Cleanly disconnect a client: leave rooms, close socket, free memory.
     */
    public function disconnect(string $clientId): void
    {
        if (!isset($this->clients[$clientId])) {
            return;
        }

        $socket     = $this->clients[$clientId]['socket'];
        $resourceId = (int) $socket;

        // Leave every room first
        foreach ($this->clients[$clientId]['rooms'] as $room) {
            $this->leave($clientId, $room);
        }

        // Close the TCP stream
        if (is_resource($socket)) {
            \fclose($socket);
        }

        // $this->log("Client disconnected: {$clientId}");
        $this->emit('disconnect', ['clientId' => $clientId]);

        // Remove from lookup maps
        unset($this->socketToClientMap[$resourceId]);
        unset($this->clients[$clientId]);
        unset($this->clientEventHandlers[$clientId]);
    }

    /**
     * O(1) reverse-lookup: stream resource → client ID.
     */
    private function getClientIdBySocket($socket)
    {
        $resourceId = (int) $socket;
        return $this->socketToClientMap[$resourceId] ?? null;
    }

    /**
     * Set persistent data for a client connection.
     */
    public function setClientData(string $clientId, string $key, $value): void
    {
        if (isset($this->clients[$clientId])) {
            $this->clients[$clientId]['customData'][$key] = $value;
        }
    }

    /**
     * Get persistent data for a client connection.
     */
    public function getClientData(string $clientId, string $key)
    {
        return $this->clients[$clientId]['customData'][$key] ?? null;
    }

    /**
     * Return all connected client metadata (including custom data).
     */
    public function getClients(): array
    {
        return $this->clients;
    }

    /**
     * Find all client IDs that have a specific piece of custom data.
     * Use dot notation for nested keys if needed (e.g., "user.id").
     * 
     * @param string $key
     * @param mixed $value
     * @return string[] Array of client IDs
     */
    public function findClientsByData(string $key, $value): array
    {
        $ids = [];
        foreach ($this->clients as $clientId => $client) {
            $current = $client['customData'] ?? [];
            
            // Handle simple dot notation for one level
            $parts = explode('.', $key);
            foreach ($parts as $part) {
                if (is_array($current) && isset($current[$part])) {
                    $current = $current[$part];
                } else {
                    $current = null;
                    break;
                }
            }

            if ($current == $value && $current !== null) {
                $ids[] = $clientId;
            }
        }
        return $ids;
    }

    /**
     * Get the number of currently connected clients.
     */
    public function getClientCount(): int
    {
        return count($this->clients);
    }

    // ─── Heartbeat ───────────────────────────────────────────────────

    /**
     * Periodically ping clients and disconnect zombies.
     */
    private function checkHeartbeat(): void
    {
        $now = microtime(true);

        // Send pings on interval
        if ($now - $this->lastPingTime >= $this->pingInterval) {
            foreach ($this->clients as $clientId => $client) {
                if ($client['handshake']) {
                    $this->sendFrame($clientId, '', 0x9);
                }
            }
            $this->lastPingTime = $now;
        }

        // Disconnect clients that missed the pong deadline
        $deadline = $this->pingInterval + $this->pingTimeout;
        foreach ($this->clients as $clientId => $client) {
            if (!$client['handshake']) {
                continue;
            }
            $elapsed = $now - $client['lastPong'];
            if ($elapsed > $deadline) {
                // $this->log("Client {$clientId} timed out ({$elapsed}s without pong)");
                $this->disconnect($clientId);
            }
        }
    }

    // ─── Server Lifecycle ────────────────────────────────────────────

    /**
     * Gracefully stop the server.
     */
    public function stop(): void
    {
        $this->running = false;

        foreach (array_keys($this->clients) as $clientId) {
            $this->disconnect($clientId);
        }

        if ($this->socket && is_resource($this->socket)) {
            \fclose($this->socket);
        }

        $this->log("WebSocket server stopped");
    }

    /**
     * Write a timestamped log line to stdout.
     */
    public function log(string $message): void
    {
        if ($this->silent) return;
        echo "[" . date('Y-m-d H:i:s') . "] {$message}\n";
    }

    public function setSilent(bool $silent): void
    {
        $this->silent = $silent;
    }

    /**
     * Handle raw data received from the internal TCP socket.
     * Decodes the JSON and emits a global server event.
     */
    private function handleInternalTrigger(string $data): void
    {
        $payload = json_decode($data, true);
        if (!$payload || !isset($payload['event'])) return;

        // Emit a global event. Controllers or Routes can listen to this.
        // We pass the server instance ($this) as the second argument.
        $this->emit($payload['event'], $payload);
    }
}
