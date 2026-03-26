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
        'rate_limit'     => 100,
        'time_window'    => 60,
        'block_duration' => 3600,
        'block_driver'   => 'file',
        'table_name'     => 'blocked_ips'
    ];

    protected static $lastMessage = null;

    /**
     * Static Factory: Ensures WAF::setBlockDriver() returns an instance for -> chaining
     */
    public static function __callStatic($name, $arguments)
    {
        $instance = new self();
        if (method_exists($instance, $name)) {
            return $instance->$name(...$arguments);
        }
    }

    /**
     * Configuration: Rate Limiting (Returns $this for chaining)
     */
    public function setRateLimit(int $limit, int $window): self
    {
        self::$config['rate_limit'] = $limit;
        self::$config['time_window'] = $window;
        return $this;
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
     * Validation: returns true if safe, false if malicious
     */
    public function validate(Request $request): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (self::isBlocked($ip)) {
            self::$lastMessage = "IP Address is currently blocked";
            return false;
        }

        if (!self::checkRateLimit($ip)) {
            self::blockIP($ip, "Rate Limit Exceeded");
            return false;
        }

        $data = array_merge($request->all(), $request->query(), $_COOKIE);
        if (self::recursiveScan($data)) {
            self::blockIP($ip, "Malicious Pattern Detected: " . self::$lastMessage);
            return false;
        }

        return true;
    }

    public function getMessage(): ?string
    {
        return self::$lastMessage;
    }

    protected static function checkRateLimit(string $ip): bool
    {
        $key = "waf_rate_" . md5($ip);
        $hits = (int)Cache::get($key, 0);
        if ($hits >= self::$config['rate_limit']) return false;
        Cache::set($key, $hits + 1, self::$config['time_window']);
        return true;
    }

    private static function recursiveScan($data): bool
    {
        if (is_array($data)) {
            foreach ($data as $v) { if (self::recursiveScan($v)) return true; }
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
        
        if (self::$config['block_driver'] === 'db') {
            DB::table(self::$config['table_name'])->insert([
                'ip_address' => $ip, 
                'reason' => $reason, 
                'expires_at' => $expiry, 
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            Cache::set("blocked_$ip", $reason, self::$config['block_duration']);
        }
        Log::error("WAF Blocking: $reason", ['ip' => $ip]);
    }

    protected static function isBlocked(string $ip): bool
    {
        if (self::$config['block_driver'] === 'db') {
            try {
                $check = DB::table(self::$config['table_name'])
                    ->where('ip_address', '=', $ip)
                    ->where('expires_at', '>', date('Y-m-d H:i:s'))
                    ->first();
                return !empty($check);
            } catch (\Exception $e) {
                // Fail safe if table still doesn't exist
                return false; 
            }
        }
        return Cache::has("blocked_$ip");
    }

    /**
     * FIXED: Correct Polyglot Schema implementation
     */
    protected static function ensureTableExists(string $tableName): void
    {
        try {
            $driver = DB::connection(); 
            $schema = new Schema($tableName, $driver);

            // Use the Schema fluent API to define metadata
            $schema->id();
            $schema->string('ip_address', 45)->unique();
            $schema->string('reason');
            $schema->timestamp('expires_at');
            $schema->timestamps();
            $schema->index(['ip_address', 'expires_at']); 

            // Hand over the blueprint to the driver for execution
            $schema->create(); 
            
        } catch (\Exception $e) {
            // Log only real errors, ignore "table already exists"
            if (strpos($e->getMessage(), 'already exists') === false && 
                strpos($e->getMessage(), '1050') === false) {
                Log::error("WAF Schema Error: " . $e->getMessage());
            }
        }
    }
}

/* ============================
Usage

use Framework\Core\Security\WAF;

WAF::check($request);

or 

WAF::setBlockDriver('db')
   ->setRateLimit(20, 10) // 20 requests per 10 seconds
   ->setBlockDuration(7200) // 2 hour block
   ->scan($request);

or

WAF::scan($request)->setBlockDuration(86400); // 24 hour block

or 

$waf = new WAF();
$waf->setBlockDriver('db');
$waf->setRateLimit(50, 60);
$waf->scan($request);


or 

WAF::setRateLimit(10, 60)->scan($request);

or 

WAF::check($request)->setBlockDuration(3600);

============================ */
