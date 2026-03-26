<?php

namespace Framework\Core\WebSocket;

/**
 * Room — a fluent wrapper for room-scoped operations on a WebSocketClient.
 *
 * Instead of repeating the room name on every call:
 *
 *   $client->toRoom('lobby', 'Hello');
 *   $client->leave('lobby');
 *
 * You can grab a Room handle and work with it directly:
 *
 *   $lobby = $client->room('lobby');
 *   $lobby->join();
 *   $lobby->send('Hello');
 *   $lobby->on('room_message', fn($data) => echo $data['message']);
 *   $lobby->leave();
 */
class Room
{
    private $client;
    private $name;
    private $eventHandlers = [];

    public function __construct(WebSocketClient $client, string $name)
    {
        $this->client = $client;
        $this->name = $name;
    }

    /**
     * Join this room.
     */
    public function join(): self
    {
        $this->client->join($this->name);
        return $this;
    }

    /**
     * Leave this room.
     */
    public function leave(): self
    {
        $this->client->leave($this->name);
        return $this;
    }

    /**
     * Send a message to this room.
     *
     *   $room->send('Hello everyone!');
     */
    public function send(string $message): self
    {
        $this->client->toRoom($this->name, $message);
        return $this;
    }

    /**
     * Emit a custom event scoped to this room.
     *
     *   $room->emit('typing', ['user' => 'Alice']);
     *
     * Optionally pass a callback for server acknowledgment:
     *
     *   $room->emit('get-members', null, function ($response) {
     *       print_r($response);
     *   });
     */
    public function emit(string $event, $data = null, $ack = null): self
    {
        $this->client->emit($event, [
            'room' => $this->name,
            'data' => $data,
        ], $ack);
        return $this;
    }

    /**
     * Emit an event scoped to this room and block until the server responds.
     *
     *   $members = $room->emitWithAck('get-members', null, 5.0);
     *
     * @param string $event   Event name
     * @param mixed  $data    Payload
     * @param float  $timeout Max seconds to wait
     * @return mixed Server's reply, or null on timeout
     */
    public function emitWithAck(string $event, $data = null, float $timeout = 5.0)
    {
        return $this->client->emitWithAck($event, [
            'room' => $this->name,
            'data' => $data,
        ], $timeout);
    }

    /**
     * Listen for events in this room.
     * The handler only fires when the event data contains this room's name.
     *
     *   $room->on('room_message', function($data) {
     *       echo "{$data['from']}: {$data['message']}\n";
     *   });
     */
    public function on(string $event, callable $handler): self
    {
        $roomName = $this->name;

        // Wrap the handler to filter by room
        $wrapped = function ($data, $client) use ($handler, $roomName) {
            $eventRoom = $data['room'] ?? null;
            if ($eventRoom === $roomName) {
                call_user_func($handler, $data, $client);
            }
        };

        $this->client->on($event, $wrapped);
        $this->eventHandlers[] = ['event' => $event, 'handler' => $wrapped];

        return $this;
    }

    /**
     * Check if the client is currently in this room.
     */
    public function isJoined(): bool
    {
        return $this->client->inRoom($this->name);
    }

    /**
     * Get the room name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * String representation.
     */
    public function __toString(): string
    {
        $status = $this->isJoined() ? 'joined' : 'not-joined';
        return "Room({$this->name}, {$status})";
    }
}
