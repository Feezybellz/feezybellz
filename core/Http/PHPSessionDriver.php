<?php

namespace Framework\Core\Http;

class PHPSessionDriver implements SessionDriverInterface
{
    protected bool $started = false;

    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                $this->applyCookieConfig();
            }
            if (!@session_start()) {
                return false;
            }
        }

        $this->started = true;

        // Rotate flash: current __flash becomes __old_flash for this request.
        if (!isset($_SESSION['__flash'])) {
            $_SESSION['__flash'] = [];
        }
        $_SESSION['__old_flash'] = $_SESSION['__flash'];
        $_SESSION['__flash'] = [];

        return true;
    }

    /**
     * Apply developer-supplied cookie/session settings from config/session.php.
     * Every field has a safe default so leaving the config file untouched
     * matches the framework's previous behavior.
     */
    protected function applyCookieConfig(): void
    {
        $cfg = function_exists('config') ? config('session', []) : [];
        $cfg = is_array($cfg) ? $cfg : [];

        $secureCfg = $cfg['cookie_secure'] ?? null;
        $secure = ($secureCfg === null) ? $this->isHttps() : (bool) $secureCfg;

        $samesite = $cfg['cookie_samesite'] ?? 'Lax';
        $samesite = in_array($samesite, ['Lax', 'Strict', 'None'], true) ? $samesite : 'Lax';
        // SameSite=None requires Secure per browser spec.
        if ($samesite === 'None' && !$secure) {
            $samesite = 'Lax';
        }

        session_set_cookie_params([
            'lifetime' => (int) ($cfg['cookie_lifetime'] ?? 0),
            'path'     => (string) ($cfg['cookie_path'] ?? '/'),
            'domain'   => (string) ($cfg['cookie_domain'] ?? ''),
            'secure'   => $secure,
            'httponly' => (bool) ($cfg['cookie_httponly'] ?? true),
            'samesite' => $samesite,
        ]);

        if (!empty($cfg['cookie_name'])) {
            session_name((string) $cfg['cookie_name']);
        }
        if (!empty($cfg['save_path']) && is_writable((string) $cfg['save_path'])) {
            session_save_path((string) $cfg['save_path']);
        }

        ini_set('session.use_strict_mode', ($cfg['use_strict_mode'] ?? true) ? '1' : '0');
    }

    public function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function clear(): void
    {
        session_unset();
    }

    public function flash(string $key, $value): void
    {
        $_SESSION['__flash'][$key] = $value;
    }

    public function getFlash(string $key, $default = null)
    {
        return $_SESSION['__old_flash'][$key] ?? $default;
    }

    /**
     * Re-flash one key so it survives another request. Reads from
     * __old_flash (the entries carried in from the previous request) and
     * writes back into __flash (the entries that will be carried to the
     * next request).
     */
    public function keep(string $key): void
    {
        if (isset($_SESSION['__old_flash'][$key])) {
            $_SESSION['__flash'][$key] = $_SESSION['__old_flash'][$key];
        }
    }

    public function reflash(): void
    {
        if (!empty($_SESSION['__old_flash'])) {
            $_SESSION['__flash'] = array_merge(
                $_SESSION['__flash'] ?? [],
                $_SESSION['__old_flash']
            );
        }
    }

    public function regenerate(): bool
    {
        // Regeneration cannot succeed once headers have been sent (the new
        // session cookie can't reach the browser). Skip loudly-throwing in
        // that case — Auth::login() calls this during flows that may have
        // already flushed a header. Real-request flow calls it BEFORE any
        // output; only tests/CLI can hit the fallback path.
        if (headers_sent()) {
            return false;
        }
        return @session_regenerate_id(true);
    }

    public function id(): string
    {
        return session_id();
    }

    private function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    }
}
