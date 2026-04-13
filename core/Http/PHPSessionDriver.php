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
}
