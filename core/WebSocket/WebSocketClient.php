<?php

namespace Framework\Core\WebSocket;

/**
 * WebSocket Client — high-level PHP API for connecting to the WebSocket server.
 *
 * Supports ws:// and wss:// (SSL/TLS), full URL parsing, and domain names.
 *
 * Usage:
 *
 *   // Simple (host + port)
 *   $client = new WebSocketClient('localhost', 8080);
 *
 *   // Full URL with domain
 *   $client = WebSocketClient::url('ws://chat.example.com:8080/ws');
 *
 *   // Secure WebSocket
 *   $client = WebSocketClient::url('wss://chat.example.com/ws');
 *
 *   // Connect and use
 *   $client->on('message', fn($data) => echo "{$data['from']}: {$data['message']}\n");
 *   $client->connect();
 *   $client->join('lobby');
 *   $client->emit('message', 'Hello everyone!');
 *   $client->toRoom('lobby', 'Hi room!');
 *
 *   // Non-blocking: process pending events
 *   $client->listen(0.5);
 *
 *   // Blocking: run an event loop forever
 *   $client->loop();
 */
class WebSocketClient
{
    private $host;
    private $port;
    private $path;
    private $secure;
    /** @var resource|null */
    private $socket = null;
    private $connected = false;
    private $buffer = '';
    private $clientId = '';
    private $rooms = [];
    private $eventHandlers = [];
    private $pendingFrames = [];
    private $pendingAcks = [];
    private $ackCounter = 0;
    private $timeout;
    private $sslOptions = [];
    private $defaultHeaders = [];

    /** Re-entrancy guard: true while listen() is draining the socket. */
    private $pumping = false;

    /**
     * Create a client with explicit host/port.
     *
     * @param string $host    Server hostname or IP
     * @param int    $port    Server port
     * @param bool   $secure  Use SSL/TLS (wss://)
     * @param int    $timeout Connection timeout in seconds
     */
    public function __construct(string $host = 'localhost', int $port = 8080, bool $secure = false, int $timeout = 5)
    {
        $this->host = $host;
        $this->port = $port;
        $this->path = '/';
        $this->secure = $secure;
        $this->timeout = $timeout;
    }

    /**
     * Create a client from a full WebSocket URL.
     *
     *   $client = WebSocketClient::url('ws://localhost:8080/chat');
     *   $client = WebSocketClient::url('wss://chat.example.com/ws');
     *   $client = WebSocketClient::url('wss://chat.example.com');      // port 443
     *   $client = WebSocketClient::url('ws://example.com');             // port 80
     *
     * @param string $url     Full WebSocket URL (ws:// or wss://)
     * @param int    $timeout Connection timeout in seconds
     * @throws \InvalidArgumentException on invalid URL
     */
    public static function url(string $url, int $timeout = 5): self
    {
        $parsed = parse_url($url);

        if ($parsed === false || !isset($parsed['host'])) {
            throw new \InvalidArgumentException("Invalid WebSocket URL: {$url}");
        }

        $scheme = strtolower($parsed['scheme'] ?? 'ws');

        if (!in_array($scheme, ['ws', 'wss'], true)) {
            throw new \InvalidArgumentException("URL scheme must be ws:// or wss://, got: {$scheme}://");
        }

        $secure = ($scheme === 'wss');
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? $parsed['port'] : ($secure ? 443 : 80);
        $path = isset($parsed['path']) ? $parsed['path'] : '/';

        if (isset($parsed['query'])) {
            $path .= '?' . $parsed['query'];
        }

        $client = new self($host, $port, $secure, $timeout);
        $client->path = $path;

        return $client;
    }

    /**
     * Configure SSL/TLS options for wss:// connections.
     *
     *   $client->ssl([
     *       'verify_peer'       => true,
     *       'verify_peer_name'  => true,
     *       'cafile'            => '/etc/ssl/certs/ca-certificates.crt',
     *       'allow_self_signed' => false,
     *   ]);
     *
     * @param array $options Stream context SSL options
     * @see https://www.php.net/manual/en/context.ssl.php
     */
    public function ssl(array $options): self
    {
        $this->sslOptions = $options;
        return $this;
    }

    /**
     * Set default headers sent with every handshake.
     * Useful for Origin, Authorization, cookies, etc.
     *
     *   $client->headers([
     *       'Origin'        => 'https://chat.example.com',
     *       'Authorization' => 'Bearer <token>',
     *       'Cookie'        => 'session=abc123',
     *   ]);
     */
    public function headers(array $headers): self
    {
        $this->defaultHeaders = $headers;
        return $this;
    }

    // ─── Connection ──────────────────────────────────────────────────

    /**
     * Connect to the WebSocket server and perform the handshake.
     *
     * @param string|null $path    URI path (overrides URL path if set)
     * @param array       $headers Extra headers for this specific connection
     * @throws \RuntimeException on failure
     */
    public function connect($path = null, array $headers = []): self
    {
        $connectPath = $path ?? $this->path;
        $transport = $this->secure ? 'ssl' : 'tcp';

        // Build stream context with SSL options if needed
        $contextOptions = [
            'socket' => ['so_reuseaddr' => true],
        ];

        if ($this->secure) {
            $contextOptions['ssl'] = array_merge([
                'verify_peer'      => true,
                'verify_peer_name' => true,
                'SNI_enabled'      => true,
                'peer_name'        => $this->host,
            ], $this->sslOptions);
        }

        $context = stream_context_create($contextOptions);

        $this->socket = stream_socket_client(
            "{$transport}://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            throw new \RuntimeException(
                "Connection to {$transport}://{$this->host}:{$this->port} failed: {$errstr} ({$errno})"
            );
        }

        stream_set_timeout($this->socket, $this->timeout);

        // Merge default headers with per-connect headers (per-connect wins)
        $mergedHeaders = array_merge($this->defaultHeaders, $headers);

        $this->performHandshake($connectPath, $mergedHeaders);
        $this->connected = true;

        // Try to read a welcome frame immediately (server typically sends one)
        $this->listen(0.5);

        return $this;
    }

    /**
     * Perform the WebSocket upgrade handshake (RFC 6455 §4.1).
     */
    private function performHandshake(string $path, array $extraHeaders): void
    {
        $key = base64_encode(random_bytes(16));

        // Build the Host header per RFC 6455 §4.1:
        // Include port only when non-default for the scheme
        $defaultPort = $this->secure ? 443 : 80;
        $hostHeader = $this->host;
        if ($this->port !== $defaultPort) {
            $hostHeader .= ':' . $this->port;
        }

        $request  = "GET {$path} HTTP/1.1\r\n";
        $request .= "Host: {$hostHeader}\r\n";
        $request .= "Upgrade: websocket\r\n";
        $request .= "Connection: Upgrade\r\n";
        $request .= "Sec-WebSocket-Key: {$key}\r\n";
        $request .= "Sec-WebSocket-Version: 13\r\n";

        // Add Origin header by default if not already provided
        if (!isset($extraHeaders['Origin']) && !isset($extraHeaders['origin'])) {
            $originScheme = $this->secure ? 'https' : 'http';
            $originHost = $this->host;
            if ($this->port !== $defaultPort) {
                $originHost .= ':' . $this->port;
            }
            $request .= "Origin: {$originScheme}://{$originHost}\r\n";
        }

        foreach ($extraHeaders as $name => $value) {
            $request .= "{$name}: {$value}\r\n";
        }

        $request .= "\r\n";

        fwrite($this->socket, $request);

        // Read the response — may contain the welcome frame bundled in
        usleep(200000);
        $response = fread($this->socket, 8192);

        if ($response === false || strpos($response, '101') === false) {
            throw new \RuntimeException("Handshake failed. Response: " . ($response ?: '(empty)'));
        }

        // Verify the accept key
        $expectedAccept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        if (strpos($response, $expectedAccept) === false) {
            throw new \RuntimeException("Invalid Sec-WebSocket-Accept in handshake response");
        }

        // If the welcome frame was bundled with the HTTP response, buffer it
        $headerEnd = strpos($response, "\r\n\r\n");
        if ($headerEnd !== false) {
            $remaining = substr($response, $headerEnd + 4);
            if (strlen($remaining) > 0) {
                $this->buffer = $remaining;
            }
        }

        stream_set_blocking($this->socket, false);
    }

    /**
     * Disconnect from the server with a clean close frame.
     */
    public function disconnect(): void
    {
        if (!$this->connected || !$this->socket) {
            return;
        }

        // Best-effort masked close frame (opcode 0x8). If the peer is
        // already gone, sendRawFrame() detects the dead socket and tears
        // down for us — don't crash (this also runs from __destruct).
        if ($this->sendRawFrame('', 0x8)) {
            usleep(100000);
            $this->teardown('client_close');
        }
    }

    /**
     * Check if the client is currently connected.
     */
    public function isConnected(): bool
    {
        if (!$this->connected || !$this->socket) {
            return false;
        }

        if (!is_resource($this->socket) || feof($this->socket)) {
            $this->connected = false;
            return false;
        }

        return true;
    }

    /**
     * Get the client ID assigned by the server (available after welcome event).
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * Get the server host.
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Get the server port.
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Whether this is a secure (wss://) connection.
     */
    public function isSecure(): bool
    {
        return $this->secure;
    }

    /**
     * Get the full WebSocket URL.
     */
    public function getUrl(): string
    {
        $scheme = $this->secure ? 'wss' : 'ws';
        $defaultPort = $this->secure ? 443 : 80;
        $portPart = ($this->port !== $defaultPort) ? ":{$this->port}" : '';
        return "{$scheme}://{$this->host}{$portPart}{$this->path}";
    }

    // ─── Events ──────────────────────────────────────────────────────

    /**
     * Register a handler for a server event.
     *
     *   $client->on('message', function(array $data) {
     *       echo $data['from'] . ': ' . $data['message'] . "\n";
     *   });
     *
     * Special local events:
     *   - 'disconnected' — fired when close() is called or server drops us
     *   - 'error'        — fired on frame/protocol errors
     */
    public function on(string $event, callable $handler): self
    {
        $this->eventHandlers[$event][] = $handler;
        return $this;
    }

    /**
     * Remove all handlers for an event (or all events if null).
     */
    public function off($event = null): self
    {
        if ($event === null) {
            $this->eventHandlers = [];
        } else {
            unset($this->eventHandlers[$event]);
        }
        return $this;
    }

    /**
     * Dispatch an event to local handlers.
     */
    private function dispatch(string $event, $data): void
    {
        if (!isset($this->eventHandlers[$event])) {
            return;
        }

        foreach ($this->eventHandlers[$event] as $handler) {
            call_user_func($handler, $data, $this);
        }
    }

    // ─── Sending ─────────────────────────────────────────────────────

    /**
     * Send a named event with data to the server.
     *
     * Optionally pass a callback to receive the server's acknowledgment
     * response (the server handler must call `$data['reply'](...)`).
     *
     *   // Fire-and-forget (existing behaviour)
     *   $client->emit('message', 'Hello!');
     *
     *   // With acknowledgment callback
     *   $client->emit('get-users', ['room' => 'lobby'], function ($response) {
     *       echo count($response['users']) . " users online\n";
     *   });
     *
     * @param string        $event    Event name
     * @param mixed         $data     Payload (any JSON-serialisable value)
     * @param callable|null $ack      Callback invoked with the server's reply
     * @return self
     */
    public function emit(string $event, $data = null, $ack = null): self
    {
        $this->requireConnection();

        // Keepalive: drain any queued inbound frames first so server pings
        // get ponged even by clients that only ever emit and never call
        // listen()/loop(). Without this, an emit-only producer looks like a
        // zombie to the server's heartbeat and gets disconnected while idle.
        $this->listen(0);
        $this->requireConnection(); // draining may have detected a close

        $payload = ['event' => $event, 'data' => $data];

        if ($ack !== null) {
            $ackId = $this->generateAckId();
            $payload['_ackId'] = $ackId;
            $this->pendingAcks[$ackId] = $ack;
        }

        if (!$this->sendRawFrame(json_encode($payload), 0x1)) {
            throw new \RuntimeException("Not connected to WebSocket server (connection lost while sending '{$event}')");
        }
        return $this;
    }

    /**
     * Emit an event and block until the server responds (or timeout).
     *
     * This is a synchronous alternative to `emit(..., $callback)`.
     * The server handler must call `$data['reply'](...)` for this to resolve.
     *
     *   $response = $client->emitWithAck('get-users', ['room' => 'lobby'], 5.0);
     *   echo count($response['users']) . " users online\n";
     *
     * @param string $event   Event name
     * @param mixed  $data    Payload
     * @param float  $timeout Max seconds to wait for the reply
     * @return mixed The server's reply data, or null on timeout
     */
    public function emitWithAck(string $event, $data = null, float $timeout = 5.0)
    {
        $result = null;
        $received = false;

        $this->emit($event, $data, function ($response) use (&$result, &$received) {
            $result = $response;
            $received = true;
        });

        $deadline = microtime(true) + $timeout;
        while (!$received && microtime(true) < $deadline) {
            $this->listen(0.05);
        }

        return $result;
    }

    /**
     * Generate a unique acknowledgment ID.
     */
    private function generateAckId(): string
    {
        return 'ack_' . (++$this->ackCounter) . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Send a raw string payload (no event wrapper).
     */
    public function sendRaw(string $payload): self
    {
        $this->requireConnection();

        // Same keepalive pump as emit() — answer pending pings first.
        $this->listen(0);
        $this->requireConnection();

        if (!$this->sendRawFrame($payload, 0x1)) {
            throw new \RuntimeException("Not connected to WebSocket server (connection lost while sending raw payload)");
        }
        return $this;
    }

    /**
     * Build and write a masked WebSocket frame to the server.
     *
     * @return bool False when the socket is dead (broken pipe / closed) —
     *              the connection is torn down locally in that case.
     */
    private function sendRawFrame(string $payload, int $opcode): bool
    {
        if (!$this->socket || !is_resource($this->socket)) {
            return false;
        }

        $length = strlen($payload);
        $mask = random_bytes(4);

        // Header: FIN + opcode
        $frame = chr(0x80 | $opcode);

        // Length + mask bit
        if ($length < 126) {
            $frame .= chr(0x80 | $length);
        } elseif ($length < 65536) {
            $frame .= chr(0x80 | 126) . pack('n', $length);
        } else {
            $frame .= chr(0x80 | 127) . pack('J', $length);
        }

        $frame .= $mask;

        // XOR-mask the payload
        for ($i = 0; $i < $length; $i++) {
            $frame .= $payload[$i] ^ $mask[$i % 4];
        }

        // Suppressed: a broken pipe raises a PHP warning (which strict
        // error handlers escalate to an exception) — handle it as a normal
        // "peer went away" event instead of crashing the caller.
        $written = @fwrite($this->socket, $frame);

        if ($written === false || $written === 0) {
            $this->teardown('write_failed');
            return false;
        }

        return true;
    }

    /**
     * Locally tear the connection down without attempting further writes
     * (the socket is already dead or dying).
     */
    private function teardown(string $reason): void
    {
        $wasConnected = $this->connected;

        $this->connected = false;
        $this->rooms = [];
        $this->clientId = '';

        if ($this->socket && is_resource($this->socket)) {
            @fclose($this->socket);
        }
        $this->socket = null;

        if ($wasConnected) {
            $this->dispatch('disconnected', ['reason' => $reason]);
        }
    }

    // ─── Rooms ───────────────────────────────────────────────────────

    /**
     * Join a room on the server.
     *
     *   $client->join('lobby');
     *   $client->join('game-42');
     */
    public function join(string $room): self
    {
        $this->emit('join', ['room' => $room]);
        $this->rooms[$room] = true;
        return $this;
    }

    /**
     * Leave a room on the server.
     */
    public function leave(string $room): self
    {
        $this->emit('leave', ['room' => $room]);
        unset($this->rooms[$room]);
        return $this;
    }

    /**
     * Send a message to a specific room.
     *
     *   $client->toRoom('lobby', 'Hello lobby!');
     */
    public function toRoom(string $room, string $message): self
    {
        $this->emit('room_message', [
            'room' => $room,
            'message' => $message,
        ]);
        return $this;
    }

    /**
     * Check if the client is currently in a room.
     */
    public function inRoom(string $room): bool
    {
        return isset($this->rooms[$room]);
    }

    /**
     * Get all rooms the client has joined.
     */
    public function getRooms(): array
    {
        return array_keys($this->rooms);
    }

    /**
     * Get a Room instance for fluent room-scoped operations.
     *
     *   $room = $client->room('lobby');
     *   $room->send('Hello!');
     *   $room->leave();
     */
    public function room(string $room): Room
    {
        return new Room($this, $room);
    }

    // ─── Receiving ───────────────────────────────────────────────────

    /**
     * Non-blocking: read and process any pending server messages.
     *
     * @param float $waitSeconds Max time to wait for data (0 = non-blocking peek)
     * @return int Number of events dispatched
     */
    public function listen(float $waitSeconds = 0): int
    {
        if (!$this->isConnected()) {
            return 0;
        }

        // Re-entrancy guard: emit() pumps the socket via listen(0), and a
        // dispatched handler may itself call emit() — don't recurse.
        if ($this->pumping) {
            return 0;
        }
        $this->pumping = true;

        try {
            return $this->doListen($waitSeconds);
        } finally {
            $this->pumping = false;
        }
    }

    /**
     * The actual read/dispatch loop behind listen().
     */
    private function doListen(float $waitSeconds): int
    {
        $dispatched = 0;
        $deadline = microtime(true) + $waitSeconds;

        do {
            // Read available data
            $data = @fread($this->socket, 65535);

            if ($data !== false && $data !== '') {
                $this->buffer .= $data;
            }

            // Process all complete frames in the buffer
            while (strlen($this->buffer) >= 2) {
                try {
                    $frame = WebSocketFrame::decode($this->buffer);
                } catch (\RuntimeException $e) {
                    $this->dispatch('error', ['message' => $e->getMessage()]);
                    $this->disconnect();
                    return $dispatched;
                }

                if (!$frame['complete']) {
                    break; // need more data
                }

                $this->buffer = substr($this->buffer, $frame['bytesRead']);

                switch ($frame['opcode']) {
                    case 0x8: // Server sent close
                        $this->teardown('server_close');
                        return $dispatched;

                    case 0x9: // Ping → reply Pong
                        if (!$this->sendRawFrame($frame['payload'], 0xA)) {
                            // Socket died while replying — already torn down.
                            return $dispatched;
                        }
                        break;

                    case 0xA: // Pong (ignore)
                        break;

                    case 0x0: // Continuation
                    case 0x1: // Text
                    case 0x2: // Binary
                        $dispatched += $this->handleDataFrame($frame);
                        break;
                }
            }

            // Small sleep to avoid busy-wait
            if (microtime(true) < $deadline) {
                usleep(10000); // 10ms
            }
        } while (microtime(true) < $deadline);

        return $dispatched;
    }

    /**
     * Blocking event loop — process events indefinitely.
     * Ideal for long-running worker scripts.
     *
     *   $client->on('message', fn($data) => handleMessage($data));
     *   $client->loop(); // runs forever
     *
     * @param float $pollInterval Seconds between poll cycles
     */
    public function loop(float $pollInterval = 0.1): void
    {
        while ($this->isConnected()) {
            $this->listen($pollInterval);
        }
    }

    /**
     * Wait for a specific event, blocking until received or timeout.
     *
     *   $data = $client->waitFor('welcome', 5.0);
     *   echo $data['clientId'];
     *
     * @param string $event    Event name to wait for
     * @param float  $timeout  Max seconds to wait
     * @return mixed Event data, or null on timeout
     */
    public function waitFor(string $event, float $timeout = 5.0)
    {
        $result = null;
        $received = false;

        // Temporarily add a handler
        $handler = function ($data) use (&$result, &$received) {
            $result = $data;
            $received = true;
        };

        $this->on($event, $handler);

        $deadline = microtime(true) + $timeout;
        while (!$received && microtime(true) < $deadline) {
            $this->listen(0.05);
        }

        // Remove the temporary handler
        if (isset($this->eventHandlers[$event])) {
            $this->eventHandlers[$event] = array_values(
                array_filter($this->eventHandlers[$event], function($h) use ($handler) { return $h !== $handler; })
            );
        }

        return $result;
    }

    /**
     * Reassemble fragmented frames, then dispatch.
     */
    private function handleDataFrame(array $frame): int
    {
        if ($frame['fin'] === 0) {
            $this->pendingFrames[] = $frame['payload'];
            return 0;
        }

        $payload = $frame['payload'];
        if (!empty($this->pendingFrames)) {
            $payload = implode('', $this->pendingFrames) . $payload;
            $this->pendingFrames = [];
        }

        $message = json_decode($payload, true);

        if (is_array($message) && isset($message['event'])) {
            // Capture the clientId from the welcome event
            if ($message['event'] === 'welcome' && isset($message['data']['clientId'])) {
                $this->clientId = $message['data']['clientId'];
            }

            // Resolve pending acknowledgment callbacks
            if ($message['event'] === '__ack__' && isset($message['data']['_ackId'])) {
                $ackId = $message['data']['_ackId'];
                if (isset($this->pendingAcks[$ackId])) {
                    $callback = $this->pendingAcks[$ackId];
                    unset($this->pendingAcks[$ackId]);
                    call_user_func($callback, $message['data']['data'] ?? null);
                    return 1;
                }
            }

            $this->dispatch($message['event'], $message['data'] ?? null);
            return 1;
        }

        // Non-JSON or structureless payload — dispatch as raw
        $this->dispatch('raw', $payload);
        return 1;
    }

    // ─── Utilities ───────────────────────────────────────────────────

    /**
     * Throw if not connected.
     */
    private function requireConnection(): void
    {
        if (!$this->isConnected()) {
            throw new \RuntimeException("Not connected to WebSocket server");
        }
    }

    /**
     * String representation for debugging.
     */
    public function __toString(): string
    {
        $status = $this->connected ? 'connected' : 'disconnected';
        $rooms = implode(', ', $this->getRooms()) ?: 'none';
        return "WebSocketClient({$this->getUrl()}, {$status}, id={$this->clientId}, rooms=[{$rooms}])";
    }

    /**
     * Clean up on destruction.
     */
    public function __destruct()
    {
        if ($this->connected) {
            $this->disconnect();
        }
    }
}
