<?php

namespace Framework\Core\WebSocket;

/**
 * ClientSocket — a per-connection wrapper, Socket.IO-style.
 *
 * Instead of manually wiring `$server->send($data['clientId'], ...)`,
 * handlers receive a `$socket` that represents the individual client:
 *
 * $server->on('connection', function ($socket) {
 * $socket->emit('welcome', ['id' => $socket->id]);
 *
 * $socket->on('chat', function ($data, $socket) {
 * $socket->emit('reply', ['got' => $data]);
 * $socket->to('lobby')->emit('chat', $data);
 * $socket->broadcast('chat', $data);
 * });
 *
 * $socket->on('disconnect', function ($data, $socket) {
 * echo "Client {$socket->id} left\n";
 * });
 * });
 */
class ClientSocket
{
    /** The unique client identifier. */
    public $id;

    /** The underlying server instance (for server-wide operations). */
    public $server;

    public function __construct(WebSocketServer $server, string $clientId)
    {
        $this->server = $server;
        $this->id = $clientId;
    }

    /**
     * Magic getter to retrieve data from the server's persistent client store.
     */
    public function __get($name)
    {
        return $this->server->getClientData($this->id, $name);
    }

    /**
     * Magic setter to persist data in the server's client store.
     */
    public function __set($name, $value)
    {
        $this->server->setClientData($this->id, $name, $value);
    }

    // ─── Sending ─────────────────────────────────────────────────────

    /**
     * Send a named event to THIS client.
     *
     * $socket->emit('welcome', ['message' => 'Hello!']);
     */
    public function emit(string $event, $data = null): self
    {
        $this->server->send($this->id, $event, $data);
        return $this;
    }

    /**
     * Broadcast an event to ALL clients EXCEPT this one.
     *
     * $socket->broadcast('user-joined', ['id' => $socket->id]);
     */
    public function broadcast(string $event, $data = null): self
    {
        $this->server->broadcast($event, $data, $this->id);
        return $this;
    }

    /**
     * Get a room-scoped emitter that excludes this client.
     *
     * $socket->to('lobby')->emit('chat', $message);
     * $socket->to('lobby')->emit('typing', ['user' => $socket->id]);
     *
     * @return ClientSocketRoom
     */
    public function to(string $room): ClientSocketRoom
    {
        return new ClientSocketRoom($this->server, $room, $this->id);
    }

    /**
     * Alias for to(). 
     * Get a room-scoped emitter that excludes this client.
     * $socket->room('lobby')->emit('chat', $message);
     *
     * @return ClientSocketRoom
     */
    public function room(string $room): ClientSocketRoom
    {
        return $this->to($room);
    }

    // ─── Rooms ───────────────────────────────────────────────────────

    /**
     * Join a room.
     *
     * $socket->join('lobby');
     */
    public function join(string $room): self
    {
        $this->server->join($this->id, $room);
        return $this;
    }

    /**
     * Leave a room.
     *
     * $socket->leave('lobby');
     */
    public function leave(string $room): self
    {
        $this->server->leave($this->id, $room);
        return $this;
    }

    /**
     * Get all rooms this client is in.
     */
    public function getRooms(): array
    {
        return $this->server->getClientRooms($this->id);
    }

    /**
     * Check if this client is in a specific room.
     */
    public function inRoom(string $room): bool
    {
        return in_array($room, $this->getRooms(), true);
    }

    // ─── Per-Socket Events ───────────────────────────────────────────

    /**
     * Register a handler for events FROM this specific client.
     *
     * This is the Socket.IO pattern — nest `on()` inside the
     * `connection` handler to scope listeners to one client:
     *
     * $server->on('connection', function ($socket) {
     * $socket->on('chat', function ($data, $socket, $reply) {
     * $socket->emit('echo', $data);
     * });
     * });
     *
     * Per-socket handlers receive: ($data, $socket, $reply)
     * - $data  — the raw event payload (unwrapped, no clientId wrapper)
     * - $socket — this ClientSocket instance
     * - $reply — callable if client expects ack, or null
     */
    public function on(string $event, callable $handler): self
    {
        $this->server->onClient($this->id, $event, $handler);
        return $this;
    }

    // ─── Connection ──────────────────────────────────────────────────

    /**
     * Disconnect this client.
     */
    public function disconnect(): void
    {
        $this->server->disconnect($this->id);
    }

    /**
     * Log a message to the server console.
     */
    public function log(string $message): void
    {
        $this->server->log($message);
    }

    /**
     * String representation.
     */
    public function __toString(): string
    {
        $rooms = implode(', ', $this->getRooms()) ?: 'none';
        return "ClientSocket({$this->id}, rooms=[{$rooms}])";
    }
}