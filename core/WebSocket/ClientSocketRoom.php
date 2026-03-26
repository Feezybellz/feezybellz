<?php

namespace Framework\Core\WebSocket;

/**
 * ClientSocketRoom — room-scoped emitter returned by $socket->to('room').
 *
 * Sends events to all clients in the room EXCEPT the originating socket.
 *
 *   $socket->to('lobby')->emit('chat', ['msg' => 'Hello lobby!']);
 */
class ClientSocketRoom
{
    private $server;
    private $room;
    private $excludeClientId;

    public function __construct(WebSocketServer $server, string $room, string $excludeClientId)
    {
        $this->server = $server;
        $this->room = $room;
        $this->excludeClientId = $excludeClientId;
    }

    /**
     * Send an event to all clients in this room, excluding the sender.
     *
     *   $socket->to('lobby')->emit('message', ['text' => 'Hi!']);
     */
    public function emit(string $event, $data = null): void
    {
        $this->server->toRoom($this->room, $event, $data, $this->excludeClientId);
    }
}
