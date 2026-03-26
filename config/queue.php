<?php

/**
 * =============================================================================
 * Queue Configuration
 * =============================================================================
 *
 * Default host and port for the in-memory queue server.
 *
 * These values are used by:
 *   - QueueServeCommand (php console queue:serve) — as defaults
 *   - QueueClient::dispatch() — to know where to connect
 *
 * You can override these at runtime:
 *   - Server:  php console queue:serve --host=0.0.0.0 --port=8888
 *   - Client:  QueueClient::dispatch('func', [], ['port' => 8888])
 */

return [

    /**
     * The IP address or hostname the queue server binds to.
     *
     * '127.0.0.1' — accept connections from localhost only (secure default)
     * '0.0.0.0'   — accept connections from any network interface
     */
    'host' => '127.0.0.1',

    /**
     * The TCP port the queue server listens on.
     *
     * Choose a port that doesn't conflict with your web server, database,
     * or WebSocket server. Default 9090 is typically unused.
     */
    'port' => 9090,

];
