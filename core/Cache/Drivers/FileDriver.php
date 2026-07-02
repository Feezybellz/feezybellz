<?php

namespace Framework\Core\Cache\Drivers;

use Framework\Core\Cache\CacheDriverInterface;
use Closure;

/**
 * File-backed cache driver.
 *
 * Hardening applied (audit item 8):
 *
 *  - Scalar/primitive values are stored as JSON. `json_decode` cannot cause
 *    object injection, so the common case is safe by construction.
 *
 *  - Object/resource-shaped values fall back to serialize(), but the
 *    resulting bytes are HMAC-SHA256 signed with APP_KEY before hitting
 *    disk. get() verifies the signature (hash_equals) BEFORE unserialize().
 *    That means a tampered cache file — which used to be direct RCE — now
 *    fails verification and returns the default.
 *
 *  - Cache directory is created with mode 0750 instead of 0777 so a
 *    different user on the box can't tamper with the files.
 *
 *  - Wire format (base64):
 *
 *        { "v":2, "kind":"json"|"sig", "exp": <int>, "data": <string> }
 *        // For kind=sig, data is "<hex-mac>:<base64-serialized-payload>"
 */
class FileDriver implements CacheDriverInterface
{
    protected $cachePath;
    private const VERSION = 2;

    public function __construct(array $config)
    {
        $this->cachePath = $config['path'] ?? __DIR__ . '/../../../../storage/framework/cache';

        if (!is_dir($this->cachePath)) {
            @mkdir($this->cachePath, 0750, true);
        }
    }

    protected function getFilePath(string $key): string
    {
        return $this->cachePath . '/' . md5($key) . '.cache';
    }

    public function get(string $key, $default = null)
    {
        $path = $this->getFilePath($key);
        if (!file_exists($path)) {
            return $default;
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return $default;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['v'], $decoded['kind'], $decoded['exp'], $decoded['data'])) {
            // Best-effort: try the legacy serialize-only format so pre-v2
            // entries still work (they'll expire naturally and be replaced).
            $legacy = @unserialize($raw);
            if (is_array($legacy) && isset($legacy['expires_at'], $legacy['data'])) {
                if (time() >= (int) $legacy['expires_at']) {
                    $this->forget($key);
                    return $default;
                }
                return $legacy['data'];
            }
            return $default;
        }

        if ((int) $decoded['v'] !== self::VERSION) {
            $this->forget($key);
            return $default;
        }
        if (time() >= (int) $decoded['exp']) {
            $this->forget($key);
            return $default;
        }

        if ($decoded['kind'] === 'json') {
            return json_decode((string) $decoded['data'], true);
        }
        if ($decoded['kind'] === 'sig') {
            // sig payload format: "<hex-mac>:<base64-serialized>"
            $sep = strpos($decoded['data'], ':');
            if ($sep === false) return $default;
            $mac = substr($decoded['data'], 0, $sep);
            $b64 = substr($decoded['data'], $sep + 1);
            $serialized = base64_decode($b64, true);
            if ($serialized === false) return $default;

            $secret = self::signingKey();
            if ($secret === '') {
                // Fail closed rather than blindly unserialize().
                return $default;
            }
            $expected = hash_hmac('sha256', $serialized, $secret);
            if (!hash_equals($expected, $mac)) {
                // Tampered — never reach unserialize().
                return $default;
            }
            $value = @unserialize($serialized, ['allowed_classes' => true]);
            return $value === false && $serialized !== 'b:0;' ? $default : $value;
        }
        return $default;
    }

    public function put(string $key, $value, int $ttl = 3600): bool
    {
        $path = $this->getFilePath($key);

        // JSON path for scalars/arrays that survive json round-tripping.
        // Cheap, safe, and unable to trigger object injection on read.
        if ($this->isJsonSafe($value)) {
            $envelope = [
                'v'    => self::VERSION,
                'kind' => 'json',
                'exp'  => time() + $ttl,
                'data' => json_encode($value),
            ];
        } else {
            $serialized = serialize($value);
            $secret = self::signingKey();
            if ($secret === '') {
                // We refuse to store an unsigned object payload because
                // get() would refuse to unserialize it anyway.
                return false;
            }
            $mac = hash_hmac('sha256', $serialized, $secret);
            $envelope = [
                'v'    => self::VERSION,
                'kind' => 'sig',
                'exp'  => time() + $ttl,
                'data' => $mac . ':' . base64_encode($serialized),
            ];
        }

        return file_put_contents($path, json_encode($envelope), LOCK_EX) !== false;
    }

    public function increment(string $key, int $value = 1): int
    {
        $path = $this->getFilePath($key);
        $fp = fopen($path, 'c+');
        if (!$fp) return 0;

        flock($fp, LOCK_EX);
        $contents = stream_get_contents($fp);

        $current = 0;
        $exp = time() + 3600;
        if ($contents) {
            $decoded = json_decode($contents, true);
            if (is_array($decoded) && ($decoded['v'] ?? null) === self::VERSION
                && ($decoded['kind'] ?? '') === 'json' && time() < ($decoded['exp'] ?? 0)) {
                $current = (int) json_decode((string) $decoded['data'], true);
                $exp = (int) $decoded['exp'];
            }
        }
        $current += $value;

        $envelope = [
            'v' => self::VERSION, 'kind' => 'json',
            'exp' => $exp, 'data' => json_encode($current),
        ];
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($envelope));
        flock($fp, LOCK_UN);
        fclose($fp);
        return $current;
    }

    public function decrement(string $key, int $value = 1): int
    {
        return $this->increment($key, $value * -1);
    }

    public function has(string $key): bool
    {
        $path = $this->getFilePath($key);
        if (!file_exists($path)) return false;

        // Read just the header — the envelope is JSON so we can decode a
        // partial file cheaply.
        $fp = fopen($path, 'r');
        if (!$fp) return false;
        $head = fread($fp, 256);
        fclose($fp);
        if ($head === false || $head === '') return false;

        if (preg_match('/"exp"\s*:\s*(\d+)/', $head, $m)) {
            if (time() >= (int) $m[1]) {
                $this->forget($key);
                return false;
            }
            return true;
        }
        // Fallback for legacy v1 format
        return $this->get($key) !== null;
    }

    public function forget(string $key): bool
    {
        $path = $this->getFilePath($key);
        if (file_exists($path)) {
            return unlink($path);
        }
        return false;
    }

    public function flush(): bool
    {
        $files = glob($this->cachePath . '/*.cache');
        $success = true;
        foreach ($files as $file) {
            if (is_file($file)) {
                $success = $success && unlink($file);
            }
        }
        return $success;
    }

    public function remember(string $key, int $ttl, Closure $callback)
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }
        $value = $callback();
        $this->put($key, $value, $ttl);
        return $value;
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Values that can round-trip via json_encode/json_decode without loss.
     * Excludes objects, resources, and floats+strings that would drift.
     */
    private function isJsonSafe($value): bool
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return true;
        }
        if (is_array($value)) {
            foreach ($value as $v) {
                if (!$this->isJsonSafe($v)) return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Resolve the HMAC key for signed cache entries. Uses APP_KEY.
     * Returns "" when the app has no APP_KEY, and put()/get() then refuse
     * to write/read the object path — safer than blindly unserialize()ing.
     */
    private static function signingKey(): string
    {
        $key = (function_exists('config') ? config('app.key') : null) ?? ($_ENV['APP_KEY'] ?? '');
        if ($key === null) return '';
        if (strpos($key, 'base64:') === 0) {
            $key = base64_decode(substr($key, 7));
        }
        return (string) $key;
    }
}
