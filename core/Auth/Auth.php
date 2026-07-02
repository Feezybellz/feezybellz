<?php

namespace Framework\Core\Auth;

/**
 * Static facade for the framework's Auth layer.
 *
 * Contract summary:
 *   Auth::check()                 // is anyone here?
 *   Auth::user()                  // whatever the developer stashed at login
 *   Auth::id()                    // best-effort ID extraction (id / user_id / sub)
 *   Auth::login($payload)         // stash payload; SessionGuard rotates
 *                                 //   session ID; JwtGuard returns a token
 *   Auth::logout()                // clear current-guard state
 *   Auth::attempt($verifier)      // call $verifier(); on non-null return,
 *                                 //   log in whatever it returned
 *   Auth::guard($name)            // switch guards for one call
 *
 * Framework helpers built on APP_KEY-signed tokens (no server storage):
 *   Auth::rememberSigned($p, $d)      // sign a "remember me" payload
 *   Auth::verifyRemember($token)       // verify one
 *   Auth::signedResetLink($p, $ttl)    // sign a "password reset" payload
 *   Auth::verifySignedLink($token)     // verify one
 *
 * Framework NEVER inspects what the developer puts in the payload. No
 * "User" class, no "UserProvider" interface, no "retrieveById" call.
 */
class Auth
{
    protected static ?GuardManager $manager = null;

    /**
     * Swap the underlying manager. Primarily for tests.
     */
    public static function useManager(GuardManager $manager): void
    {
        self::$manager = $manager;
    }

    public static function manager(): GuardManager
    {
        if (self::$manager === null) {
            self::$manager = new GuardManager();
        }
        return self::$manager;
    }

    /**
     * Reset the resolved-guard cache. Called by State::resetPerRequest()
     * in long-running SAPIs.
     */
    public static function reset(): void
    {
        if (self::$manager !== null) {
            self::$manager->reset();
        }
    }

    public static function guard(?string $name = null): Guard
    {
        return self::manager()->guard($name);
    }

    // ─── Default-guard convenience ────────────────────────────────────

    public static function check(): bool { return self::guard()->check(); }
    public static function user()        { return self::guard()->user(); }

    /**
     * Best-effort ID accessor. Looks for common keys/properties in this
     * order: `id`, `user_id`, `sub`. Returns null if none of those exist —
     * developers with custom payloads should just do `Auth::user()->foo`.
     *
     * @return mixed
     */
    public static function id()
    {
        $u = self::user();
        if ($u === null) return null;
        foreach (['id', 'user_id', 'sub'] as $key) {
            if (is_array($u) && array_key_exists($key, $u)) {
                return $u[$key];
            }
            if (is_object($u) && isset($u->{$key})) {
                return $u->{$key};
            }
        }
        return null;
    }

    /**
     * Log in via the default guard. Return value is guard-specific — a
     * JwtGuard returns the minted token string, SessionGuard returns null.
     */
    public static function login($payload)
    {
        return self::guard()->login($payload);
    }

    public static function logout(): void
    {
        self::guard()->logout();
    }

    /**
     * Convenience: run a verifier callable that returns "the thing to
     * stash on success" or null/false on failure. If it succeeds, log in
     * with the result and return the guard's login() return value
     * (typically a token for JWT, null for session). On failure, return
     * `false` and don't touch the guard.
     *
     * Developer never has to spell "if (found) login(found)" for the
     * common case, and framework still doesn't assume credential shape.
     *
     * @param  callable $verifier fn(): mixed  — returns payload or null/false
     * @return mixed
     */
    public static function attempt(callable $verifier)
    {
        $result = $verifier();
        if ($result === null || $result === false) {
            return false;
        }
        return self::login($result);
    }

    // ─── Signed-payload helpers (no server storage) ───────────────────

    /**
     * Sign a "remember me" payload with APP_KEY for $days days.
     * Returns the token string — developer is responsible for setting
     * the cookie (framework has no cookie-setting facade of its own).
     */
    public static function rememberSigned($payload, int $days = 30): string
    {
        return SignedToken::issue($payload, $days * 86400);
    }

    /**
     * Verify a remember-me token. Returns the payload or null.
     * @return mixed
     */
    public static function verifyRemember(string $token)
    {
        return SignedToken::verify($token);
    }

    /**
     * Sign a payload for embedding in a password-reset link. Default TTL
     * is 1 hour — override for longer/shorter windows.
     */
    public static function signedResetLink($payload, int $ttl = 3600): string
    {
        return SignedToken::issue($payload, $ttl);
    }

    /**
     * Verify a signed-link token. Returns the payload or null.
     * @return mixed
     */
    public static function verifySignedLink(string $token)
    {
        return SignedToken::verify($token);
    }
}
