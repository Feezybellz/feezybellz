<?php

/**
 * =============================================================================
 * QueueClient — Lightweight Job Producer (TCP Socket Client)
 * =============================================================================
 *
 * This class is used by your web application (controllers, services, etc.)
 * to push jobs to the QueueServer. It opens a short-lived TCP connection,
 * sends the job payload, reads the acknowledgement, and closes the connection.
 *
 * Usage from a controller:
 *
 * // Dispatch a named function
 * QueueClient::dispatch('sendWelcomeEmail', ['user@example.com']);
 *
 * // Dispatch a static class method
 * QueueClient::dispatch(['App\\Services\\Email', 'send'], ['user@example.com', 'Hello!']);
 *
 * // With custom host/port
 * $client = new QueueClient('127.0.0.1', 9090);
 * $result = $client->push('processImage', ['/uploads/photo.jpg']);
 *
 * Wire Protocol (must match QueueServer):
 * ┌──────────────────────┬──────────────────────────────────────────┐
 * │ 4 bytes (uint32 BE)  │  JSON payload (UTF-8)                   │
 * │ = payload length     │  {"callable":...,"args":[...]}          │
 * └──────────────────────┴──────────────────────────────────────────┘
 *
 * @package Framework\Core\Queue
 */

namespace Framework\Core\Queue;
class QueueClient
{
    // ─── Properties ─────────────────────────────────────────────────────

    /** @var string The IP/hostname of the queue server to connect to. */
    private $host;

    /** @var int The TCP port of the queue server. */
    private $port;

    /**
     * Connection timeout in seconds.
     *
     * How long stream_socket_client() will wait to establish the TCP
     * connection, AND how long fread()/fwrite() will wait for a response
     * before giving up.
     *
     * @var int
     */
    private $timeout;

    // ─── Constructor ────────────────────────────────────────────────────

    /**
     * Create a new QueueClient instance.
     *
     * @param string $host     Queue server hostname/IP (default: 127.0.0.1)
     * @param int    $port     Queue server TCP port (default: 9090)
     * @param int    $timeout  Connection timeout in seconds (default: 5)
     */
    public function __construct($host = null, $port = null, $timeout = 5)
    {
        $this->host = $host ?? (function_exists('config') ? config('queue.host') : '127.0.0.1')  ;
        $this->port = $port ?? (function_exists('config') ? config('queue.port') : 9090);
        $this->timeout = $timeout ?? (function_exists('config') ? config('queue.timeout') : 5);
    }

    // ─── Public API ─────────────────────────────────────────────────────

    /**
     * Push a job onto the remote queue.
     *
     * @param string|array $callable  The PHP callable to execute on the server.
     * @param array        $args      Arguments to pass to the callable (default: [])
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function push($callable, array $args = []): array
    {
        // ── Step 1: Build & Encode the job payload ──────────────────────
        if ($callable instanceof \Closure || is_object($callable)) {
            return [
                'success' => false,
                'message' => 'Security policy prevents dispatching closures/objects. Dispatch named callables only.',
            ];
        }

        $payload = [
            'type'     => 'callable',
            'callable' => $callable,
            'args'     => $args,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return [
                'success' => false,
                'message' => 'Failed to encode job payload as JSON: ' . json_last_error_msg(),
            ];
        }

        // ── Step 2: Open a TCP connection ───────────────────────────────
        $address = "tcp://{$this->host}:{$this->port}";
        $socket = @stream_socket_client($address, $errno, $errstr, $this->timeout);

        if ($socket === false) {
            return [
                'success' => false,
                'message' => "Could not connect to queue server at {$address}: {$errstr} (errno: {$errno})",
            ];
        }

        // ── Step 3: CRITICAL TIMEOUT FIX ────────────────────────────────
        // By default, PHP uses the default_socket_timeout (usually 60s)
        // for stream reads/writes once connected. If the queue server
        // accepts the connection but hangs, the web request will freeze.
        // We strictly enforce our custom timeout for I/O operations.
        stream_set_timeout($socket, $this->timeout);

        // ── Step 4: Build & Send the length-prefixed message ────────────
        $header = pack('N', strlen($json));
        $message = $header . $json;

        $totalWritten = 0;
        $messageLength = strlen($message);

        while ($totalWritten < $messageLength) {
            $written = @fwrite($socket, substr($message, $totalWritten));

            if ($written === false || $written === 0) {
                fclose($socket);
                return [
                    'success' => false,
                    'message' => 'Failed to send job payload to queue server (connection broken during write)',
                ];
            }

            $totalWritten += $written;
        }

        // ── Step 5: Read Response & Clean Up ────────────────────────────
        $response = $this->readResponse($socket);
        
        fclose($socket);

        return $response;
    }

    /**
     * Static convenience method for one-liner job dispatching.
     *
     * $result = QueueClient::dispatch('processOrder', [$orderId]);
     *
     * @param string|array $callable  The PHP callable to execute
     * @param array        $args      Arguments for the callable
     * @param array        $options   Optional overrides: ['host', 'port', 'timeout']
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public static function dispatch($callable, array $args = [], array $options = []): array
    {
        $defaultHost = '127.0.0.1';
        $defaultPort = 9090;

        if (function_exists('config')) {
            $queueConfig = config('queue');
            if (is_array($queueConfig)) {
                $defaultHost = $queueConfig['host'] ?? $defaultHost;
                $defaultPort = $queueConfig['port'] ?? $defaultPort;
            }
        }

        $host    = $options['host']    ?? $defaultHost;
        $port    = $options['port']    ?? $defaultPort;
        $timeout = $options['timeout'] ?? 5;

        $client = new self($host, (int) $port, (int) $timeout);

        return $client->push($callable, $args);
    }

    // ─── Private Helpers ────────────────────────────────────────────────

    /**
     * Read a length-prefixed JSON response from the server.
     *
     * @param resource $socket  The connected socket to read from
     * @return array{success: bool, message: string, data?: array}
     */
    private function readResponse($socket): array
    {
        // ── Step 1: Read and Decode the 4-byte length header ────────────
        $header = $this->readExact($socket, 4);

        if ($header === false) {
            return [
                'success' => false,
                'message' => 'Failed to read response header (connection closed or stream timeout reached)',
            ];
        }

        $decoded = unpack('N', $header);
        $payloadLength = $decoded[1];

        if ($payloadLength > 1024 * 1024) {
            return [
                'success' => false,
                'message' => "Server response payload too large ({$payloadLength} bytes)",
            ];
        }

        // ── Step 2: Read and Decode the JSON payload ────────────────────
        $json = $this->readExact($socket, $payloadLength);

        if ($json === false) {
            return [
                'success' => false,
                'message' => 'Failed to read response payload from queue server',
            ];
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

    /**
     * Read exactly $length bytes from a socket.
     *
     * @param resource $socket  The socket to read from
     * @param int      $length  Exact number of bytes to read
     *
     * @return string|false  The data read, or false on failure/timeout
     */
    private function readExact($socket, int $length)
    {
        $buffer = '';
        $remaining = $length;

        while ($remaining > 0) {
            $chunk = @fread($socket, $remaining);

            // fread() returns false on error, or an empty string on EOF.
            if ($chunk === false || ($chunk === '' && feof($socket))) {
                return false;
            }

            // Because this stream is blocking, if fread returns an empty 
            // string WITHOUT hitting EOF, it means the stream_set_timeout 
            // limit was reached before the bytes arrived.
            if ($chunk === '') {
                return false;
            }

            $buffer .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $buffer;
    }

    /**
     * Check if the queue server is currently running.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        $stats = $this->getStats();
        return $stats['success'] === true;
    }

    /**
     * Static convenience method to check if the queue is online.
     *
     * @param array $options Optional overrides: ['host', 'port', 'timeout']
     * @return bool
     */
    public static function isOnline(array $options = []): bool
    {
        $defaultHost = '127.0.0.1';
        $defaultPort = 9090;

        if (function_exists('config')) {
            $queueConfig = config('queue');
            if (is_array($queueConfig)) {
                $defaultHost = $queueConfig['host'] ?? $defaultHost;
                $defaultPort = $queueConfig['port'] ?? $defaultPort;
            }
        }

        $host    = $options['host']    ?? $defaultHost;
        $port    = $options['port']    ?? $defaultPort;
        $timeout = $options['timeout'] ?? 2; // Shorter timeout for status check

        $client = new self($host, (int) $port, (int) $timeout);
        return $client->isRunning();
    }

    /**
     * Retrieve the current statistics and state from the QueueServer.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function getStats(): array
    {
        // Send a payload with 'command' instead of 'callable'
        $payload = ['command' => 'stats'];
        $json = json_encode($payload);

        $address = "tcp://{$this->host}:{$this->port}";
        $socket = @stream_socket_client($address, $errno, $errstr, $this->timeout);

        if ($socket === false) {
            return [
                'success' => false,
                'message' => 'Queue Server is offline.',
            ];
        }

        stream_set_timeout($socket, $this->timeout);

        $header = pack('N', strlen($json));
        $message = $header . $json;

        @fwrite($socket, $message);

        $response = $this->readResponse($socket);
        fclose($socket);

        return $response;
    }
}
