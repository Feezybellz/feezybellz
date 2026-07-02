<?php

/**
 * QueueServer (in-memory TCP daemon) configuration.
 *
 * ╔════════════════════════════════════════════════════════════════════╗
 * ║  REMOTE CODE EXECUTION SURFACE                                    ║
 * ║                                                                    ║
 * ║  QueueServer accepts serialized job payloads and executes them    ║
 * ║  inside forked child processes. A malicious payload that reaches   ║
 * ║  the enqueue path is effectively RCE on this host.                 ║
 * ║                                                                    ║
 * ║  This file configures THREE independent defenses:                  ║
 * ║                                                                    ║
 * ║   1. `bind_host` and `allowed_peers` — only accept connections     ║
 * ║      from trusted network locations. Default is loopback only.     ║
 * ║                                                                    ║
 * ║   2. `secret` — HMAC-SHA256 signature on every message. The        ║
 * ║      server refuses to enqueue anything whose signature does not   ║
 * ║      verify with hash_equals against this key.                     ║
 * ║                                                                    ║
 * ║   3. `allowed_callables` — allowlist of PHP callables the server   ║
 * ║      is willing to invoke. Default is EMPTY, which means no jobs   ║
 * ║      can be dispatched until you list at least one pattern.        ║
 * ║                                                                    ║
 * ║  Do not disable any of these unless you fully understand what      ║
 * ║  you're giving up.                                                 ║
 * ╚════════════════════════════════════════════════════════════════════╝
 *
 * Wire protocol (v2):
 *
 *   ┌──────────────────────┬──────────────────────┬──────────────────┐
 *   │ 4 bytes (uint32 BE)  │ 32 bytes             │ N bytes          │
 *   │ = 32 + N             │ HMAC-SHA256          │ JSON payload     │
 *   └──────────────────────┴──────────────────────┴──────────────────┘
 *
 * The HMAC is computed over the JSON bytes exactly as they appear on the wire
 * (no re-encoding). The client MUST use the same bytes it sends over the
 * socket to compute the signature.
 */

return [

    /*
    | Host/port the server binds to. Loopback is the ONLY safe default.
    |
    | If you set bind_host to 0.0.0.0, make sure allowed_peers contains
    | the exact IPs (not "0.0.0.0") of the machines you actually trust
    | to talk to this server, AND that the secret is set.
    */
    'bind_host' => env('QUEUE_SERVER_HOST', '127.0.0.1'),
    'bind_port' => (int) env('QUEUE_SERVER_PORT', 9090),

    /*
    | HMAC key. Every incoming and outgoing frame is signed with this.
    | Falls back to APP_KEY (which is fine — the queue server never
    | authenticates end users, only worker-to-daemon calls).
    |
    | Empty string DISABLES signature enforcement. That is meant for
    | initial local development ONLY. Never leave this empty in any
    | environment that faces a network.
    */
    'secret' => env('QUEUE_SERVER_SECRET', env('APP_KEY', '')),

    /*
    | Whether to require a signature on every message.
    | Almost always leave this true. Set false only for one-shot debugging.
    */
    'require_signature' => filter_var(env('QUEUE_SERVER_REQUIRE_SIG', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Peer IPs (or CIDR prefixes) allowed to connect.
    | Anything else is closed at accept() without reading a byte.
    |
    | Formats accepted:
    |   '127.0.0.1'          exact match
    |   '::1'                exact match (IPv6)
    |   '10.0.0.0/8'         CIDR range
    |   '192.168.1.0/24'     CIDR range
    */
    'allowed_peers' => ['127.0.0.1', '::1'],

    /*
    | Callable allowlist. Patterns supported:
    |
    |   'App\Jobs\SendEmail::handle'   exact match
    |   'App\Jobs\*::handle'           any class in namespace with :handle
    |   'App\Jobs\SendEmail::*'        any method on that class
    |   'App\Jobs\**::*'               any class recursively, any method
    |   'sendEmail'                    a bare function name
    |
    | Default is empty — no jobs can be dispatched until at least one
    | pattern is listed. This is a security default, not a bug.
    */
    'allowed_callables' => [
        // 'App\Jobs\**::handle',
    ],

    /*
    | Also gate low-privilege introspection commands (stats). If false,
    | the `stats` command is available without a signature check — useful
    | for shipping metrics to a local Prometheus scraper. Default is to
    | require the signature.
    */
    'allow_unsigned_stats' => false,

    /*
    | HTTP UI (optional). When enabled the daemon also binds a second port
    | that serves a dashboard. The dashboard leaks queue state, so gate it
    | with a token — the browser must supply ?token=<value> in the URL.
    |
    | Set ui.port to null to disable the UI entirely (recommended in prod).
    */
    'ui' => [
        'enabled' => filter_var(env('QUEUE_SERVER_UI_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'port'    => env('QUEUE_SERVER_UI_PORT') ? (int) env('QUEUE_SERVER_UI_PORT') : null,
        'token'   => env('QUEUE_SERVER_UI_TOKEN', ''),
    ],

    /*
    | Operational knobs (unchanged from the original defaults).
    */
    'max_children'             => (int) env('QUEUE_SERVER_MAX_CHILDREN', 5),
    'batch_size'               => (int) env('QUEUE_SERVER_BATCH_SIZE', 10),
    'max_jobs_before_restart'  => (int) env('QUEUE_SERVER_MAX_JOBS', 10000),
    'max_clients'              => (int) env('QUEUE_SERVER_MAX_CLIENTS', 100),

];
