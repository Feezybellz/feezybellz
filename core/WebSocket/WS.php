<?php

namespace Framework\Core\WebSocket;

class WS
{
    /**
     * Broadcast an event to all connected WebSocket clients globally.
     *
     * @param string $event The event name (e.g., 'new_post')
     * @param array $data The JSON payload to send
     */
    public static function broadcast(string $event, array $data = []): void
    {
        self::send([
            'event' => 'broadcast',
            'data' => [
                'event' => $event,
                'payload' => $data
            ]
        ]);
    }

    /**
     * Send an event specifically to a room.
     *
     * @param string $room The room name (e.g., 'chat_room_1')
     * @param string $event The event name
     * @param array $data The JSON payload to send
     */
    public static function to(string $room, string $event, array $data = []): void
    {
        self::send([
            'event' => 'room_broadcast',
            'data' => [
                'room' => $room,
                'event' => $event,
                'payload' => $data
            ]
        ]);
    }

    /**
     * Send raw payload to the internal trigger port asynchronously.
     */
    private static function send(array $payload): void
    {
        $port = config('app.websocket.internal_port', 8081);
        
        // Suppress errors if the WS server isn't currently running
        $fp = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 1);
        
        if ($fp) {
            // Write the JSON payload to the daemon and instantly close the stream
            fwrite($fp, json_encode($payload));
            fclose($fp);
        }
    }
}
