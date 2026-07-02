<?php

namespace Framework\Core\Http;

/**
 * Session facade over a SessionDriverInterface.
 *
 * IMPORTANT — session regeneration on privilege change.
 *
 * Any code path that changes the user's privilege level (login, logout,
 * role change, MFA step-up) MUST call `session()->regenerate()` after
 * writing the new state. Without regeneration, an attacker who planted a
 * session ID in the victim's browser BEFORE login retains a valid ID
 * AFTER login — classic session fixation.
 *
 *     public function login(Request $r) {
 *         if ($this->credentialsOk($r)) {
 *             session()->set('user_id', $userId);
 *             session()->regenerate();   // ← REQUIRED
 *             return response()->redirect('/dashboard');
 *         }
 *     }
 *
 * Cookie settings — lifetime, domain, path, secure, httponly, samesite,
 * cookie name, save path — are all developer-configurable via
 * config/session.php. Session::start() applies them once per process.
 */
class Session
{
    protected SessionDriverInterface $driver;

    public function __construct(SessionDriverInterface $driver)
    {
        $this->driver = $driver;
        $this->driver->start();
    }

    public function get(string $key, $default = null)
    {
        return $this->driver->get($key, $default);
    }

    public function set(string $key, $value): void
    {
        $this->driver->set($key, $value);
    }

    public function has(string $key): bool
    {
        return $this->driver->has($key);
    }

    public function remove(string $key): void
    {
        $this->driver->remove($key);
    }

    public function clear(): void
    {
        $this->driver->clear();
    }

    public function flash(string $key, $value): void
    {
        $this->driver->flash($key, $value);
    }

    public function getFlash(string $key, $default = null)
    {
        return $this->driver->getFlash($key, $default);
    }

    /**
     * Re-flash a value so it survives one more request cycle.
     * Reads the current old_flash entry and re-writes it into new_flash.
     * Useful for redirect-chain flows where the user hasn't yet reached
     * the page that consumes the flash data.
     */
    public function keep(string $key): void
    {
        if (method_exists($this->driver, 'keep')) {
            $this->driver->keep($key);
            return;
        }
        // Generic fallback via the public flash interface.
        $existing = $this->driver->getFlash($key);
        if ($existing !== null) {
            $this->driver->flash($key, $existing);
        }
    }

    /**
     * Re-flash EVERY flash entry from the previous request. Handy for
     * multi-hop redirect flows where you don't want to enumerate keys.
     */
    public function reflash(): void
    {
        if (method_exists($this->driver, 'reflash')) {
            $this->driver->reflash();
        }
    }

    public function regenerate(): bool
    {
        return $this->driver->regenerate();
    }

    public function id(): string
    {
        return $this->driver->id();
    }
}
