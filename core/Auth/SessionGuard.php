<?php

namespace Framework\Core\Auth;

use Framework\Core\Http\Session;

/**
 * Session-backed guard.
 *
 * The bag is `session()->get('_auth_<name>')`. Whatever the developer
 * passes to login() gets stashed there; user() reads it back; logout()
 * clears it.
 *
 * login() ALWAYS regenerates the session ID — that's the framework's
 * session-fixation defence. If a caller has an edge case where regen is
 * inappropriate (impersonation etc.), they can bypass this guard and
 * write to the session directly, but that's opt-out rather than opt-in.
 */
class SessionGuard implements Guard
{
    private Session $session;
    private string  $sessionKey;

    /**
     * Request-scoped cache of the resolved payload, so repeated calls in
     * one request don't re-fetch from the session store.
     * @var mixed
     */
    private $cached = null;
    private bool $resolved = false;

    public function __construct(Session $session, string $sessionKey = '_auth')
    {
        $this->session    = $session;
        $this->sessionKey = $sessionKey;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function user()
    {
        if (!$this->resolved) {
            $this->cached   = $this->session->get($this->sessionKey);
            $this->resolved = true;
        }
        return $this->cached;
    }

    /**
     * Stash the payload in the session and rotate the session ID.
     * Returns null — the browser already has the session cookie; there's
     * no token to hand back to the caller.
     */
    public function login($payload)
    {
        $this->session->set($this->sessionKey, $payload);
        // Session-fixation defence: any ID an attacker planted in the
        // victim's browser BEFORE login is now invalid.
        $this->session->regenerate();
        $this->cached   = $payload;
        $this->resolved = true;
        return null;
    }

    public function logout(): void
    {
        $this->session->remove($this->sessionKey);
        // Second regeneration on logout so a leaked session cookie can't
        // be used to reach state that was there before logout.
        $this->session->regenerate();
        $this->cached   = null;
        $this->resolved = true;
    }
}
