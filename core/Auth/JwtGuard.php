<?php

namespace Framework\Core\Auth;

use Framework\Core\Http\Request;

/**
 * Stateless JWT-bearer guard.
 *
 * check()/user() read `Authorization: Bearer <token>` from the current
 * request, verify with the framework's hardened JWT class (alg lock,
 * iss/aud/nbf validation, leeway, key rotation), and return the payload.
 *
 * login($payload) mints a fresh token containing $payload as an opaque
 * `data` claim and RETURNS the token string. Nothing is stored server-
 * side — that's the point of JWT. The developer is responsible for
 * sending the returned token to the client (usually in the JSON response
 * body from a `/login` endpoint).
 *
 * logout() is a client-side operation from JWT's perspective: server has
 * nothing to invalidate. If the app needs true revocation, wire a
 * blocklist into a CallableGuard or maintain one alongside this guard.
 */
class JwtGuard implements Guard
{
    private Request $request;
    private int $ttl;

    /** @var mixed */
    private $cached  = null;
    private bool $resolved = false;

    /**
     * Last token minted via login(), so a controller can grab it after a
     * successful attempt() without having to catch the return value.
     */
    private ?string $lastToken = null;

    public function __construct(Request $request, int $ttl = 3600)
    {
        $this->request = $request;
        $this->ttl     = $ttl;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function user()
    {
        if ($this->resolved) {
            return $this->cached;
        }
        $this->resolved = true;

        $token = $this->request->getBearerToken();
        if (!is_string($token) || $token === '') {
            return $this->cached = null;
        }

        $decoded = JWT::decode($token);
        if (!is_array($decoded)) {
            return $this->cached = null;
        }

        // We wrap the developer's payload under a `data` claim at login()
        // to keep it isolated from JWT's reserved claims (iat/exp/iss/aud).
        // Older tokens without a `data` claim fall through to the raw
        // payload for backward compatibility.
        return $this->cached = $decoded['data'] ?? $decoded;
    }

    /**
     * Mint a JWT containing $payload and return the token string.
     * The caller is responsible for delivering it to the client.
     */
    public function login($payload)
    {
        if (json_encode($payload) === false) {
            throw new \InvalidArgumentException(
                "JwtGuard::login() payload must be JSON-encodable."
            );
        }

        $token = JWT::encode(['data' => $payload], null, $this->ttl);

        $this->cached    = $payload;
        $this->resolved  = true;
        $this->lastToken = $token;

        return $token;
    }

    /**
     * JWT is stateless — logout() has no server-side effect. Clears the
     * request-scoped cache so a subsequent check()/user() call in the
     * same request returns null.
     */
    public function logout(): void
    {
        $this->cached    = null;
        $this->resolved  = true;
        $this->lastToken = null;
    }

    /**
     * The token from the most recent login() call on this guard, or null.
     * Convenience for controllers that used Auth::attempt() and want the
     * token without catching a return value.
     */
    public function lastToken(): ?string
    {
        return $this->lastToken;
    }
}
