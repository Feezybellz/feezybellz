<?php

namespace Framework\Core\WebSocket;

/**
 * ConnectionManager — manage multiple WebSocket client connections.
 *
 * Useful when your backend service needs to maintain several connections,
 * e.g. a microservice that listens to different rooms on different servers,
 * or a test harness simulating multiple users.
 *
 * Usage:
 *
 *   $manager = new ConnectionManager();
 *
 *   // Add named connections
 *   $manager->add('admin', 'localhost', 8080);
 *   $manager->add('worker', 'localhost', 8080);
 *
 *   // Connect all
 *   $manager->connectAll();
 *
 *   // Work with individual connections
 *   $manager->get('admin')->join('admin-panel');
 *   $manager->get('worker')->join('job-queue');
 *
 *   // Poll all connections for incoming events
 *   $manager->listenAll(0.5);
 *
 *   // Disconnect everything
 *   $manager->disconnectAll();
 */
class ConnectionManager
{
    /** @var array<string, WebSocketClient> */
    private $connections = [];

    /**
     * Create and register a named connection (does NOT connect yet).
     *
     * @param string $name    A unique name for this connection
     * @param string $host    Server host
     * @param int    $port    Server port
     * @param int    $timeout Connection timeout
     * @return WebSocketClient The created client (for immediate chaining)
     */
    public function add(string $name, string $host = 'localhost', int $port = 8080, bool $secure = false, int $timeout = 5): WebSocketClient
    {
        $client = new WebSocketClient($host, $port, $secure, $timeout);
        $this->connections[$name] = $client;
        return $client;
    }

    /**
     * Register an existing WebSocketClient under a name.
     */
    public function register(string $name, WebSocketClient $client): self
    {
        $this->connections[$name] = $client;
        return $this;
    }

    /**
     * Get a connection by name.
     *
     * @throws \RuntimeException if the name is not registered
     */
    public function get(string $name): WebSocketClient
    {
        if (!isset($this->connections[$name])) {
            throw new \RuntimeException("Connection '{$name}' not found. Available: " . implode(', ', array_keys($this->connections)));
        }

        return $this->connections[$name];
    }

    /**
     * Check if a named connection exists.
     */
    public function has(string $name): bool
    {
        return isset($this->connections[$name]);
    }

    /**
     * Remove a connection by name (disconnects first if still connected).
     */
    public function remove(string $name): self
    {
        if (isset($this->connections[$name])) {
            if ($this->connections[$name]->isConnected()) {
                $this->connections[$name]->disconnect();
            }
            unset($this->connections[$name]);
        }

        return $this;
    }

    /**
     * Connect all registered connections that aren't already connected.
     *
     * @param string $path    URI path
     * @param array  $headers Extra headers
     */
    public function connectAll(string $path = '/', array $headers = []): self
    {
        foreach ($this->connections as $name => $client) {
            if (!$client->isConnected()) {
                $client->connect($path, $headers);
            }
        }

        return $this;
    }

    /**
     * Disconnect all connections.
     */
    public function disconnectAll(): self
    {
        foreach ($this->connections as $client) {
            if ($client->isConnected()) {
                $client->disconnect();
            }
        }

        return $this;
    }

    /**
     * Poll all connections for incoming events (non-blocking).
     *
     * @param float $waitSeconds Max time per connection
     * @return int Total events dispatched across all connections
     */
    public function listenAll(float $waitSeconds = 0): int
    {
        $total = 0;

        foreach ($this->connections as $client) {
            if ($client->isConnected()) {
                $total += $client->listen($waitSeconds);
            }
        }

        return $total;
    }

    /**
     * Run a blocking event loop for all connections.
     *
     * @param float $pollInterval Seconds between poll cycles
     */
    public function loopAll(float $pollInterval = 0.1): void
    {
        while ($this->getConnectedCount() > 0) {
            $this->listenAll($pollInterval);
        }
    }

    /**
     * Get all registered connection names.
     *
     * @return string[]
     */
    public function getNames(): array
    {
        return array_keys($this->connections);
    }

    /**
     * Get count of connections that are currently active.
     */
    public function getConnectedCount(): int
    {
        $count = 0;
        foreach ($this->connections as $client) {
            if ($client->isConnected()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get total registered connections (connected or not).
     */
    public function count(): int
    {
        return count($this->connections);
    }

    /**
     * Broadcast an event to ALL connections.
     */
    public function broadcastEmit(string $event, $data = null, $ack = null): self
    {
        foreach ($this->connections as $client) {
            if ($client->isConnected()) {
                $client->emit($event, $data, $ack);
            }
        }

        return $this;
    }

    /**
     * Clean up on destruction.
     */
    public function __destruct()
    {
        $this->disconnectAll();
    }
}
