<?php

/**
 * QueueClient — Lightweight Job Producer (TCP Socket Client)
 *
 * Speaks the QueueServer v2 wire protocol:
 *
 *   ┌──────────────────────┬──────────────────────┬──────────────────┐
 *   │ 4 bytes (uint32 BE)  │ 32 bytes             │ N bytes          │
 *   │ = 32 + N             │ HMAC-SHA256(sec,json)│ JSON payload     │
 *   └──────────────────────┴──────────────────────┴──────────────────┘
 *
 * The signature is REQUIRED unless the server explicitly runs with
 * `require_signature=false` (dev only). The signing key is read from
 * config('queue_server.secret') → env('QUEUE_SERVER_SECRET') → env('APP_KEY').
 *
 * Server responses use the same framing. When require_signature is on and a
 * key is available, the client also verifies the server's signature before
 * returning the parsed response — that way a MITM on the loopback bus can't
 * forge a success response for a dropped job.
 */

namespace Framework\Core\Queue;

class QueueClient
{
    /** @var string */
    private $host;
    /** @var int */
    private $port;
    /** @var int */
    private $timeout;
    /** @var string */
    private $secret;

    public function __construct($host = null, $port = null, $timeout = 5, ?string $secret = null)
    {
        $this->host = $host ?? (function_exists('config') ? config('queue_server.bind_host', config('queue.host', '127.0.0.1')) : '127.0.0.1');
        $this->port = $port ?? (function_exists('config') ? config('queue_server.bind_port', config('queue.port', 9090)) : 9090);
        $this->timeout = $timeout ?? 5;
        $this->secret = $secret ?? self::resolveSecret();
    }

    /**
     * Resolve the HMAC secret. Preference order:
     *   1. Explicit constructor arg.
     *   2. config('queue_server.secret').
     *   3. env('QUEUE_SERVER_SECRET').
     *   4. env('APP_KEY').
     * Base64-prefixed keys are unwrapped so hash_hmac gets raw bytes.
     */
    private static function resolveSecret(): string
    {
        $secret = '';
        if (function_exists('config')) {
            $secret = (string) (config('queue_server.secret') ?? '');
        }
        if ($secret === '') {
            $secret = (string) (getenv('QUEUE_SERVER_SECRET') ?: getenv('APP_KEY') ?: '');
        }
        if (strpos($secret, 'base64:') === 0) {
            $secret = base64_decode(substr($secret, 7));
        }
        return $secret;
    }

    /**
     * Push a job onto the remote queue.
     */
    public function push($callable, array $args = []): array
    {
        if ($callable instanceof \Closure || is_object($callable)) {
            return [
                'success' => false,
                'message' => 'Security policy prevents dispatching closures/objects. Dispatch named callables only.',
            ];
        }

        return $this->sendFrame([
            'type'     => 'callable',
            'callable' => $callable,
            'args'     => $args,
        ]);
    }

    /**
     * Static convenience method for one-liner job dispatching.
     */
    public static function dispatch($callable, array $args = [], array $options = []): array
    {
        $defaultHost = '127.0.0.1';
        $defaultPort = 9090;

        if (function_exists('config')) {
            $defaultHost = config('queue_server.bind_host', config('queue.host', $defaultHost));
            $defaultPort = (int) config('queue_server.bind_port', config('queue.port', $defaultPort));
        }

        $host    = $options['host']    ?? $defaultHost;
        $port    = $options['port']    ?? $defaultPort;
        $timeout = $options['timeout'] ?? 5;
        $secret  = $options['secret']  ?? null;

        $client = new self($host, (int) $port, (int) $timeout, $secret);
        return $client->push($callable, $args);
    }

    public function isRunning(): bool
    {
        return $this->getStats()['success'] === true;
    }

    public static function isOnline(array $options = []): bool
    {
        $defaultHost = '127.0.0.1';
        $defaultPort = 9090;

        if (function_exists('config')) {
            $defaultHost = config('queue_server.bind_host', config('queue.host', $defaultHost));
            $defaultPort = (int) config('queue_server.bind_port', config('queue.port', $defaultPort));
        }

        $host    = $options['host']    ?? $defaultHost;
        $port    = $options['port']    ?? $defaultPort;
        $timeout = $options['timeout'] ?? 2;
        $secret  = $options['secret']  ?? null;

        $client = new self($host, (int) $port, (int) $timeout, $secret);
        return $client->isRunning();
    }

    public function getStats(): array
    {
        return $this->sendFrame(['command' => 'stats']);
    }

    // ─── Wire ─────────────────────────────────────────────────────────

    private function sendFrame(array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return [
                'success' => false,
                'message' => 'Failed to encode job payload as JSON: ' . json_last_error_msg(),
            ];
        }

        $address = "tcp://{$this->host}:{$this->port}";
        $socket = @stream_socket_client($address, $errno, $errstr, $this->timeout);
        if ($socket === false) {
            return [
                'success' => false,
                'message' => "Could not connect to queue server at {$address}: {$errstr} (errno: {$errno})",
            ];
        }
        stream_set_timeout($socket, $this->timeout);

        // Sign — 32 zero bytes when no secret available. The server will
        // reject the frame if it has require_signature=true, which is the
        // correct behavior — misconfiguration should fail loudly.
        $sig = $this->secret !== ''
            ? hash_hmac('sha256', $json, $this->secret, true)
            : str_repeat("\0", 32);

        $frame = pack('N', 32 + strlen($json)) . $sig . $json;

        $totalWritten = 0;
        $len = strlen($frame);
        while ($totalWritten < $len) {
            $written = @fwrite($socket, substr($frame, $totalWritten));
            if ($written === false || $written === 0) {
                fclose($socket);
                return [
                    'success' => false,
                    'message' => 'Failed to send job payload to queue server (connection broken during write)',
                ];
            }
            $totalWritten += $written;
        }

        $response = $this->readResponse($socket);
        fclose($socket);
        return $response;
    }

    /**
     * Read a signed length-prefixed response frame from the server.
     */
    private function readResponse($socket): array
    {
        $header = $this->readExact($socket, 4);
        if ($header === false) {
            return [
                'success' => false,
                'message' => 'Failed to read response header (connection closed or stream timeout reached)',
            ];
        }

        $decoded = unpack('N', $header);
        $totalLen = $decoded[1];

        if ($totalLen > (1024 * 1024) + 32) {
            return [
                'success' => false,
                'message' => "Server response payload too large ({$totalLen} bytes)",
            ];
        }
        if ($totalLen < 32) {
            return [
                'success' => false,
                'message' => "Malformed server response (frame < 32 bytes for signature)",
            ];
        }

        $sig = $this->readExact($socket, 32);
        if ($sig === false) {
            return [
                'success' => false,
                'message' => 'Failed to read response signature',
            ];
        }

        $json = $this->readExact($socket, $totalLen - 32);
        if ($json === false) {
            return [
                'success' => false,
                'message' => 'Failed to read response payload from queue server',
            ];
        }

        // Verify server signature when we have a secret. If the server sent
        // 32 zero bytes (require_signature=false on server) we accept only
        // when we too have no secret configured — otherwise we treat the
        // absence of a signature as a downgrade attempt.
        if ($this->secret !== '') {
            $expected = hash_hmac('sha256', $json, $this->secret, true);
            if (!hash_equals($expected, $sig)) {
                return [
                    'success' => false,
                    'message' => 'Server response signature invalid — possible tampering or misconfigured secret.',
                ];
            }
        }

        $response = json_decode($json, true);
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => 'Server response was not valid JSON: ' . json_last_error_msg(),
            ];
        }
        return $response;
    }

    private function readExact($socket, int $length)
    {
        $buffer = '';
        $remaining = $length;
        while ($remaining > 0) {
            $chunk = @fread($socket, $remaining);
            if ($chunk === false || ($chunk === '' && feof($socket))) {
                return false;
            }
            if ($chunk === '') {
                return false; // stream_set_timeout reached
            }
            $buffer .= $chunk;
            $remaining -= strlen($chunk);
        }
        return $buffer;
    }
}
