<?php

namespace Framework\Core\Security;

use Framework\Core\Http\Request;
use Framework\Core\Cache\Cache;
use Framework\Core\Logging\Log;
use Framework\Core\Database\DB;
use Framework\Core\Database\Schema;

class WAF
{
    protected static $rules = [
        'sqli' => '/(UNION\s+SELECT|INSERT\s+INTO|UPDATE\s+.*SET|DELETE\s+FROM|DROP\s+TABLE|--|#|\/\*|SLEEP\(|BENCHMARK\()/i',
        'xss'  => '/(<script\b[^>]*>|javascript:|onerror\s*=|onload\s*=|alert\(|eval\(|atob\(|String\.fromCharCode)/i',
        'lfi'  => '/(\.\.\/|\.\.\\\\|etc\/passwd|proc\/self\/environ|php:\/\/filter)/i',
        'rce'  => '/(system\(|exec\(|passthru\(|shell_exec\(|popen\(|base64_decode\(|`|\\\\x[0-9a-fA-F]{2})/i',
    ];

    protected static $config = [
        'block_duration' => 3600,
        'block_driver'   => 'file',
        'table_name'     => 'blocked_ips'
    ];

    protected static $lastMessage = null;

    /**
     * Static Factory: Ensures WAF::setBlockDriver() returns an instance for chaining
     */
    public static function __callStatic($name, $arguments)
    {
        $instance = new self();
        if (method_exists($instance, $name)) {
            return $instance->$name(...$arguments);
        }
        throw new \BadMethodCallException("Method {$name} does not exist on WAF.");
    }

    /**
     * Configuration: Block Driver (Returns $this for chaining)
     */
    public function setBlockDriver(string $driver, string $tableName = 'blocked_ips'): self
    {
        self::$config['block_driver'] = $driver;
        self::$config['table_name'] = $tableName;
        if ($driver === 'db') {
            self::ensureTableExists($tableName);
        }
        return $this;
    }

    /**
     * Configuration: Block Duration (Returns $this for chaining)
     */
    public function setBlockDuration(int $seconds): self
    {
        self::$config['block_duration'] = $seconds;
        return $this;
    }

    /**
     * Alias for validate()
     */
    public function scan(Request $request): bool
    {
        return $this->validate($request);
    }

    /**
     * Alias for validate()
     */
    public function check(Request $request): bool
    {
        return $this->validate($request);
    }

    /**
     * Validation: returns true if safe, false if malicious
     */
    public function validate(Request $request): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (self::isBlocked($ip)) {
            self::$lastMessage = "IP Address is currently blocked";
            return false;
        }

        // We explicitly omit rate limiting here (ThrottleRequests handles that).
        // Only checking malicious payloads.

        $data = array_merge($request->all(), $request->query(), $_COOKIE);
        if (self::recursiveScan($data)) {
            self::blockIP($ip, "Malicious Pattern Detected: " . self::$lastMessage);
            return false;
        }

        return true;
    }

    public static function getMessage(): ?string
    {
        return self::$lastMessage;
    }

    private static function recursiveScan($data): bool
    {
        if (is_array($data)) {
            foreach ($data as $v) { 
                if (self::recursiveScan($v)) return true; 
            }
            return false;
        }
        
        foreach (self::$rules as $type => $regex) {
            if (preg_match($regex, (string)$data)) {
                self::$lastMessage = $type;
                return true;
            }
        }
        return false;
    }

    protected static function blockIP(string $ip, string $reason): void
    {
        self::$lastMessage = $reason;
        $expiry = date('Y-m-d H:i:s', time() + self::$config['block_duration']);
        $host = $_SERVER['HTTP_HOST'] ?? 'global';
        $cacheKey = "blocked_{$host}_{$ip}";
        
        if (self::$config['block_driver'] === 'db') {
            try {
                DB::table(self::$config['table_name'])->insert([
                    'ip_address' => $ip, 
                    'reason' => $reason, 
                    'expires_at' => $expiry, 
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } catch (\Exception $e) {
                // Fallback to cache if DB fails
                Cache::set($cacheKey, $reason, self::$config['block_duration']);
            }
        } else {
            Cache::set($cacheKey, $reason, self::$config['block_duration']);
        }
        
        if (class_exists('\Framework\Core\Logging\Log')) {
            Log::error("WAF Blocking: $reason", ['ip' => $ip, 'host' => $host]);
        }
    }

    protected static function isBlocked(string $ip): bool
    {
        if (self::$config['block_driver'] === 'db') {
            try {
                // If the table check fails (doesn't exist), we just assume false.
                $check = DB::table(self::$config['table_name'])
                    ->where('ip_address', '=', $ip)
                    ->where('expires_at', '>', date('Y-m-d H:i:s'))
                    ->first();
                return !empty($check);
            } catch (\Exception $e) {
                return false; 
            }
        }
        
        $host = $_SERVER['HTTP_HOST'] ?? 'global';
        return Cache::has("blocked_{$host}_{$ip}");
    }

    /**
     * Creates the table but caches the check so it doesn't kill performance.
     */
    protected static function ensureTableExists(string $tableName): void
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'global';
        // PERFORMANCE FIX: Only check once an hour max per server instance, scoped by TENANT HOST
        $cacheKey = "waf_table_exists_{$host}_{$tableName}";
        if (Cache::has($cacheKey)) {
            return;
        }

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
            
            Cache::set($cacheKey, true, 3600); // Remember for 1 hour
        } catch (\Exception $e) {
            // If it already exists, that's fine, cache it!
            if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), '1050') !== false) {
                Cache::set($cacheKey, true, 3600);
            } else {
                if (class_exists('\Framework\Core\Logging\Log')) {
                    Log::error("WAF Schema Error: " . $e->getMessage());
                }
            }
        }
    }
}
