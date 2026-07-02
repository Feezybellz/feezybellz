<?php

namespace Framework\Core\Auth;

/**
 * The single abstraction every auth mechanism satisfies.
 *
 * Framework never introspects the payload — whatever the developer stashes
 * via login() comes back from user(). Could be an array, an object, a bare
 * ID, an ORM record, a DTO. Framework has no opinion about "user."
 */
interface Guard
{
    /**
     * True when this guard has resolved somebody for the current request.
     */
    public function check(): bool;

    /**
     * The opaque payload — whatever login() stored, or null.
     *
     * @return mixed
     */
    public function user();

    /**
     * Mark someone as authenticated for the current request.
     *
     * Return value varies by driver:
     *   - SessionGuard  → null (side effect: session set + rotated)
     *   - JwtGuard      → the signed token string (developer sends to client)
     *   - CallableGuard → whatever the developer's loginHandler returns
     *
     * @param  mixed $payload
     * @return mixed
     */
    public function login($payload);

    /**
     * Clear whatever authentication state exists.
     */
    public function logout(): void;
}
