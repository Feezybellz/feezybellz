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

    /**
     * -------------------------------------------------------------------------
     * Driver-based queue (Queue::push / Queue::pop / queue:work)
     * -------------------------------------------------------------------------
     *
     * Separate from the in-memory queue server above. These keys configure
     * the Queue facade used by `php console queue:work` and by queued event
     * listeners.
     *
     * Drivers: 'redis' (php-redis extension) or 'database' (uses the
     * _framework_jobs table — create it with `php console queue:table`
     * followed by `php console migrate`).
     */
    'default' => env('QUEUE_DRIVER', 'redis'),

    'connections' => [

        'redis' => [
            'driver'      => 'redis',
            'host'        => env('REDIS_HOST', '127.0.0.1'),
            'port'        => (int) env('REDIS_PORT', 6379),
            'password'    => env('REDIS_PASSWORD', ''),
            'database'    => (int) env('REDIS_QUEUE_DB', 0),
            'prefix'      => 'framework_queue:',
            // Seconds before a crashed worker's reservation is reclaimed.
            // Must exceed your longest-running job.
            'retry_after' => 90,
        ],

        'database' => [
            'driver'       => 'database',
            'table'        => '_framework_jobs',
            'failed_table' => '_framework_failed_jobs',
            // Seconds before a crashed worker's reservation is reclaimed.
            // Must exceed your longest-running job.
            'retry_after'  => 90,
        ],

    ],

];
