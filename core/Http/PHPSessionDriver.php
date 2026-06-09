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
                $params = session_get_cookie_params();
                session_set_cookie_params([
                    'lifetime' => $params['lifetime'] ?? 0,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => $this->isHttps(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
                ini_set('session.use_strict_mode', '1');
            }

            // Use @ to suppress "headers already sent" warnings in CLI/Tests
            if (!@session_start()) {
                return false;
            }
        }

        $this->started = true;
        
        // 1. If we have current flash data (set late in the last script), it becomes "old" for this request.
        if (!isset($_SESSION['__flash'])) {
            $_SESSION['__flash'] = [];
        }
        
        $_SESSION['__old_flash'] = $_SESSION['__flash'];
        $_SESSION['__flash'] = [];

        return true;
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

    public function regenerate(): bool
    {
        return session_regenerate_id(true);
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
