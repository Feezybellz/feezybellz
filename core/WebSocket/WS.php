<?php

namespace Framework\Core\WebSocket;

/**
 * Synchronous PHP → WebSocket-server bridge.
 *
 * Pushes events to the running WebSocket daemon via its internal trigger
 * port. Every payload is HMAC-signed (same posture as QueueServer): the
 * internal port binds to loopback, but loopback alone does not
 * authenticate — any local process/user on a shared box could otherwise
 * inject broadcasts to every connected client.
 *
 * Wire format (signed envelope):
 *
 *   { "v": 1, "ts": <unix>, "payload": "<exact JSON string>",
 *     "sig": "<hex hmac_sha256(ts . '.' . payload, secret)>" }
 *
 * The secret defaults to APP_KEY (config `app.websocket.internal_secret`
 * to override), so a correctly-deployed app is secure with zero extra
 * configuration.
 */
class WS
{
    /** Seconds an envelope timestamp may deviate before rejection. */
    public const REPLAY_WINDOW = 30;

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
     * Resolve the HMAC secret for the internal trigger port.
     *
     * Order: app.websocket.internal_secret → APP_KEY. A `base64:` prefix
     * is unwrapped so hash_hmac gets raw bytes. Empty string = unsigned
     * (only acceptable in dev with require_internal_signature=false).
     */
    public static function internalSecret(): string
    {
        $secret = '';
        if (function_exists('config')) {
            $secret = (string) (config('app.websocket.internal_secret') ?? '');
            if ($secret === '') {
                $secret = (string) (config('app.key') ?? '');
            }
        }

        if (strpos($secret, 'base64:') === 0) {
            $secret = base64_decode(substr($secret, 7));
        }

        return $secret;
    }

    /**
     * Build a signed envelope for an internal-trigger payload.
     * Public and static so the server side (and tests) verify against the
     * exact same format.
     */
    public static function buildEnvelope(array $payload, string $secret, ?int $ts = null): string
    {
        $json = json_encode($payload);
        $ts = $ts ?? time();

        return json_encode([
            'v'       => 1,
            'ts'      => $ts,
            'payload' => $json,
            'sig'     => hash_hmac('sha256', $ts . '.' . $json, $secret),
        ]);
    }

    /**
     * Send a payload to the internal trigger port asynchronously.
     */
    private static function send(array $payload): void
    {
        $port = config('app.websocket.internal_port', 8081);

        $secret = self::internalSecret();
        $wire = ($secret !== '')
            ? self::buildEnvelope($payload, $secret)
            : json_encode($payload); // unsigned legacy (dev only)

        // Suppress errors if the WS server isn't currently running
        $fp = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 1);

        if ($fp) {
            // Write the payload to the daemon and instantly close the stream
            fwrite($fp, $wire);
            fclose($fp);
        }
    }
}
