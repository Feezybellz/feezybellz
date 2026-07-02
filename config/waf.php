<?php

/**
 * Web Application Firewall configuration.
 *
 * The WAF is defense-in-depth, not a substitute for input validation or
 * prepared statements. It catches obvious probe payloads and blocks the
 * source IP for `block_duration` seconds.
 *
 * The pattern set below is deliberately case-insensitive and word-boundary-
 * anchored. Attackers who newline-inject or use encoded payloads will get
 * through — that's fine. Your real defenses live at the SQL/query layer.
 */

return [

    /*
    | Where to record blocks. 'file' uses the framework Cache; 'db' persists
    | into the configured table.
    */
    'block_driver' => env('WAF_DRIVER', 'file'),
    'table_name'   => env('WAF_TABLE', 'blocked_ips'),
    'block_duration' => (int) env('WAF_BLOCK_DURATION', 3600),

    /*
    | Trusted proxy CIDRs. When the request source IP is one of these,
    | the WAF (and Request::ip()) will honor X-Forwarded-For / X-Real-IP.
    | Otherwise those headers are IGNORED and only REMOTE_ADDR is used.
    |
    | This is the fix for the "attacker spoofs X-Forwarded-For to get some
    | other user's IP blocked" DoS. Set this to your load balancer's CIDR.
    | Leave empty in single-node deployments — REMOTE_ADDR is authoritative
    | there.
    */
    'trusted_proxies' => array_filter(array_map('trim', explode(',', (string) env('WAF_TRUSTED_PROXIES', '')))),

    /*
    | Malicious pattern set. Add/remove as the app needs. Each pattern is
    | tested case-insensitively against the JSON-encoded request payload.
    */
    'patterns' => [
        // SQL injection probes
        'sqli' => '/\b(UNION\s+(?:ALL\s+)?SELECT|INSERT\s+INTO|UPDATE\s+\w+\s+SET|DELETE\s+FROM|DROP\s+TABLE|--\s|\#\s|\/\*.*?\*\/|SLEEP\s*\(|BENCHMARK\s*\()/i',
        // XSS probes
        'xss'  => '/(<script\b[^>]*>|javascript:\s*[a-z]|on(?:error|load|click|mouseover)\s*=|<iframe\b|<svg\b[^>]*on|document\.cookie|window\.location\s*=)/i',
        // Local file inclusion
        'lfi'  => '/(\.\.\/|\.\.\\\\|\/etc\/(?:passwd|shadow|hosts)|\/proc\/self\/environ|php:\/\/(?:filter|input|memory))/i',
        // Remote code execution
        'rce'  => '/\b(system|exec|passthru|shell_exec|popen|proc_open|assert|create_function)\s*\(|base64_decode\s*\(|\\\\x[0-9a-fA-F]{2}|`[^`]*`/i',
    ],

    /*
    | Content types the WAF should scan. Skip binary uploads to save CPU.
    */
    'scan_content_types' => [
        'application/json',
        'application/x-www-form-urlencoded',
        'multipart/form-data',
        'text/plain',
    ],
];
