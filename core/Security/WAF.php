<?php

namespace Framework\Core\Security;

use Framework\Core\Http\Request;
use Framework\Core\Cache\Cache;
use Framework\Core\Logging\Log;
use Framework\Core\Database\DB;
use Framework\Core\Database\Schema;

/**
 * Web Application Firewall — defense-in-depth pattern matcher.
 *
 * Reads its posture from config/waf.php. Key hardening:
 *
 *  1. Trusted-proxy peer resolution — X-Forwarded-For / X-Real-IP is only
 *     honored when the immediate peer (REMOTE_ADDR) is on the configured
 *     trusted_proxies list. Otherwise the WAF uses REMOTE_ADDR directly.
 *     This closes the "attacker spoofs XFF header to blocklist someone
 *     else's IP" DoS.
 *
 *  2. Content-type gating — skip binary uploads to save CPU.
 *
 *  3. Config-driven pattern set with tightened boundaries so common false
 *     positives (like the word "update" appearing in normal English input)
 *     don't trigger.
 */
class WAF
{
    protected static $lastMessage = null;

    /** Static factory for chained configuration. */
    public static function __callStatic($name, $arguments)
    {
        $instance = new self();
        if (method_exists($instance, $name)) {
            return $instance->$name(...$arguments);
        }
        throw new \BadMethodCallException("Method {$name} does not exist on WAF.");
    }

    /* ── Runtime overrides for tests / adhoc tuning ──────────────────── */

    protected ?array $overrideConfig = null;

    public function setBlockDriver(string $driver, string $tableName = 'blocked_ips'): self
    {
        $this->override(['block_driver' => $driver, 'table_name' => $tableName]);
        if ($driver === 'db') {
            $this->ensureTableExists($tableName);
        }
        return $this;
    }

    public function setBlockDuration(int $seconds): self
    {
        return $this->override(['block_duration' => $seconds]);
    }

    protected function override(array $patch): self
    {
        $this->overrideConfig = ($this->overrideConfig ?? $this->readConfig()) ; // materialize
        $this->overrideConfig = array_replace($this->overrideConfig, $patch);
        return $this;
    }

    protected function readConfig(): array
    {
        if ($this->overrideConfig !== null) return $this->overrideConfig;

        $defaults = [
            'block_driver'       => 'file',
            'table_name'         => 'blocked_ips',
            'block_duration'     => 3600,
            'trusted_proxies'    => [],
            'patterns'           => [],
            'scan_content_types' => [
                'application/json',
                'application/x-www-form-urlencoded',
                'multipart/form-data',
                'text/plain',
            ],
        ];
        if (function_exists('config')) {
            $cfg = config('waf');
            if (is_array($cfg)) {
                $defaults = array_replace($defaults, $cfg);
            }
        }
        return $defaults;
    }

    public function scan(Request $request): bool  { return $this->validate($request); }
    public function check(Request $request): bool { return $this->validate($request); }

    public function validate(Request $request): bool
    {
        $cfg = $this->readConfig();
        $ip = $this->clientIp($cfg['trusted_proxies']);

        if ($this->isBlocked($ip, $cfg)) {
            self::$lastMessage = "IP Address is currently blocked";
            return false;
        }

        // Skip scanning binary/multipart-file bodies — the patterns are
        // string-oriented and running them across MB of upload payload is
        // both slow and useless.
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        $shouldScan = false;
        foreach ($cfg['scan_content_types'] as $allowed) {
            if (str_starts_with($contentType, $allowed)) { $shouldScan = true; break; }
        }
        if (!$shouldScan && $request->method() !== 'GET') {
            // Non-GET requests we can't identify are still worth a shallow scan
            // of query params, so don't short-circuit entirely.
            $payload = json_encode([$request->query(), $_COOKIE]);
        } else {
            $payload = json_encode([$request->all(), $request->query(), $_COOKIE]);
        }

        if ($this->scanString($payload, $cfg['patterns'])) {
            $this->blockIP($ip, "Malicious Pattern Detected: " . self::$lastMessage, $cfg);
            return false;
        }
        return true;
    }

    public static function getMessage(): ?string { return self::$lastMessage; }

    /**
     * Resolve the real client IP.
     *
     * Peer = REMOTE_ADDR (the box directly connected to us). If the peer is
     * on the trusted-proxy list, we walk X-Forwarded-For from right to left
     * skipping trusted-proxy hops and use the first untrusted address. If
     * the peer is NOT trusted, we return REMOTE_ADDR directly and ignore
     * any XFF header — that's what stops the spoofing DoS.
     */
    protected function clientIp(array $trustedProxies): string
    {
        $peer = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (empty($trustedProxies) || !$this->matchesAny($peer, $trustedProxies)) {
            return $peer;
        }

        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff === '') {
            return $peer;
        }

        // Right-to-left: the rightmost entry is the closest to us. Skip
        // trusted hops until we find an untrusted address — that's the client.
        $hops = array_map('trim', explode(',', $xff));
        for ($i = count($hops) - 1; $i >= 0; $i--) {
            if (!$this->matchesAny($hops[$i], $trustedProxies)) {
                return $hops[$i];
            }
        }
        return $peer;
    }

    protected function matchesAny(string $ip, array $rules): bool
    {
        foreach ($rules as $rule) {
            if ($rule === $ip) return true;
            if (strpos($rule, '/') !== false && $this->cidrMatch($ip, $rule)) return true;
        }
        return false;
    }

    protected function cidrMatch(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr, 2);
        $mask = (int) $mask;
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false || $mask < 0 || $mask > 32) {
            return false;
        }
        if ($mask === 0) return true;
        $maskLong = -1 << (32 - $mask);
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    protected function scanString(string $data, array $patterns): bool
    {
        foreach ($patterns as $type => $regex) {
            if (@preg_match($regex, $data)) {
                self::$lastMessage = $type;
                return true;
            }
        }
        return false;
    }

    /**
     * Cache key: NEVER interpolate the Host header directly (it's attacker-
     * controllable and a poor namespace source). Use a stable hash.
     */
    protected function blockCacheKey(string $ip): string
    {
        return 'waf_blocked_' . substr(sha1($ip), 0, 32);
    }

    protected function blockIP(string $ip, string $reason, array $cfg): void
    {
        self::$lastMessage = $reason;
        $expiry = date('Y-m-d H:i:s', time() + $cfg['block_duration']);

        if ($cfg['block_driver'] === 'db') {
            try {
                DB::table($cfg['table_name'])->insert([
                    'ip_address' => $ip,
                    'reason'     => $reason,
                    'expires_at' => $expiry,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Exception $e) {
                Cache::set($this->blockCacheKey($ip), $reason, $cfg['block_duration']);
            }
        } else {
            Cache::set($this->blockCacheKey($ip), $reason, $cfg['block_duration']);
        }

        if (class_exists(Log::class)) {
            Log::error("WAF blocking: {$reason}", ['ip' => $ip]);
        }
    }

    protected function isBlocked(string $ip, array $cfg): bool
    {
        if ($cfg['block_driver'] === 'db') {
            try {
                $check = DB::table($cfg['table_name'])
                    ->where('ip_address', '=', $ip)
                    ->where('expires_at', '>', date('Y-m-d H:i:s'))
                    ->first();
                return !empty($check);
            } catch (\Exception $e) {
                return false;
            }
        }
        return Cache::has($this->blockCacheKey($ip));
    }

    protected function ensureTableExists(string $tableName): void
    {
        // One-per-hour cache (per-table, no host in the key).
        $cacheKey = 'waf_table_exists_' . substr(sha1($tableName), 0, 16);
        if (Cache::has($cacheKey)) return;

        try {
            $driver = DB::connection();
            $schema = new Schema($tableName, $driver);
            $schema->id();
            $schema->string('ip_address', 45)->unique();
            $schema->string('reason');
            $schema->timestamp('expires_at');
            $schema->timestamps();
            $schema->index(['ip_address', 'expires_at']);
            $schema->create();
            Cache::set($cacheKey, true, 3600);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'already exists') !== false || strpos($msg, '1050') !== false) {
                Cache::set($cacheKey, true, 3600);
            } elseif (class_exists(Log::class)) {
                Log::error("WAF Schema Error: {$msg}");
            }
        }
    }
}
