<?php

namespace Framework\Core\Auth;

use Framework\Core\Http\Request;

/**
 * Escape hatch for auth schemes that don't fit Session or JWT:
 *   - Basic auth
 *   - Long-lived API keys
 *   - HMAC-signed requests
 *   - Any custom header/query/body-based identification
 *
 * Developer wires it up with two-to-three callables and never has to
 * touch guard internals:
 *
 *     $auth->extend('apikey', fn () => new CallableGuard(
 *         request(),
 *         resolver:      fn (Request $r) => MyKeys::find($r->header('X-API-Key')),
 *         loginHandler:  null,           // API keys aren't "logged into"
 *         logoutHandler: null,           // ...or out of
 *     ));
 *
 * The resolver takes the current Request and returns whatever "the user"
 * is (payload can be any shape). Framework caches the result for the
 * request lifetime so repeated Auth::user() calls don't re-run the
 * resolver.
 */
class CallableGuard implements Guard
{
    private Request  $request;
    /** @var callable */
    private $resolver;
    /** @var callable|null */
    private $loginHandler;
    /** @var callable|null */
    private $logoutHandler;

    /** @var mixed */
    private $cached = null;
    private bool $resolved = false;

    public function __construct(
        Request $request,
        callable $resolver,
        ?callable $loginHandler = null,
        ?callable $logoutHandler = null
    ) {
        $this->request        = $request;
        $this->resolver       = $resolver;
        $this->loginHandler   = $loginHandler;
        $this->logoutHandler  = $logoutHandler;
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
        return $this->cached = ($this->resolver)($this->request);
    }

    /**
     * If the guard was set up with a loginHandler, delegate to it. If not
     * — the guard is read-only (e.g. inspecting a Basic-auth header) —
     * throw so misuse is loud.
     */
    public function login($payload)
    {
        if ($this->loginHandler === null) {
            throw new \BadMethodCallException(
                "This CallableGuard was constructed without a loginHandler — "
                . "it's a read-only identity resolver. To make it writable, "
                . "pass a loginHandler callable in the constructor."
            );
        }
        $result = ($this->loginHandler)($payload);
        $this->cached   = $payload;
        $this->resolved = true;
        return $result;
    }

    public function logout(): void
    {
        if ($this->logoutHandler !== null) {
            ($this->logoutHandler)();
        }
        $this->cached   = null;
        $this->resolved = true;
    }
}
