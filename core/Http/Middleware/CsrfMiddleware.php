<?php

namespace Framework\Core\Http\Middleware;

use Framework\Core\Http\Middleware;
use Framework\Core\Http\Request;
use Framework\Core\Http\Response;

/**
 * CSRF middleware (opt-in).
 *
 * The framework does NOT register this globally. A developer enables CSRF for
 * a route or group by applying the `csrf` alias (or `csrf:<profile>` for a
 * named profile in config/csrf.php).
 *
 *   Router::post('/users', 'csrf', [UserController::class, 'store']);
 *
 *   Router::middleware('csrf', function () {
 *       Router::post('/orders', [OrderController::class, 'store']);
 *       Router::patch('/orders/{id}', [OrderController::class, 'update']);
 *   });
 *
 *   // Strict profile — ignore the `except` list inside this group:
 *   Router::middleware('csrf:strict', function () { ... });
 *
 *   // `skip` profile — locally disable CSRF inside a parent group that has it:
 *   Router::middleware('csrf', function () {
 *       Router::middleware('csrf:skip', function () {
 *           Router::post('/webhooks/stripe', [Webhooks::class, 'stripe']);
 *       });
 *   });
 *
 * Behavior:
 *  - Read-safe methods (GET/HEAD/OPTIONS) are always allowed through.
 *  - URIs matched by the profile's `except` patterns are allowed through.
 *  - For anything else, the submitted token (body field or one of the
 *    configured headers) must equal the session token. Comparison is
 *    constant-time (hash_equals).
 *  - Failure: 419 JSON for JSON clients, 419 HTML otherwise.
 */
class CsrfMiddleware implements Middleware
{
    public function handle(Request $request, callable $next, array $params = []): Response
    {
        $profile = $this->resolveProfile($params);

        // Read-only methods need no token.
        if ($this->isReadMethod($request)) {
            return $next($request);
        }

        // Profile may declare that no methods need checking (e.g. the `skip` profile).
        $methods = array_map('strtoupper', $profile['methods']);
        if (!in_array(strtoupper($request->method()), $methods, true)) {
            return $next($request);
        }

        if ($this->isExcluded($request->uri(), $profile['except'])) {
            return $next($request);
        }

        $sessionToken = session()->get('_csrf_token');
        $requestToken = $this->getTokenFromRequest($request, $profile);

        $valid = is_string($sessionToken)
            && is_string($requestToken)
            && $sessionToken !== ''
            && hash_equals($sessionToken, $requestToken);

        if ($valid) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return Response::setStatusCode(419)->json([
                'success' => false,
                'message' => 'Invalid CSRF token.',
            ]);
        }
        return Response::setStatusCode(419)->html('<h1>419</h1><p>Invalid CSRF token.</p>');
    }

    /**
     * Resolve the configured profile, validating it before use.
     */
    protected function resolveProfile(array $params): array
    {
        $name = $params[0] ?? config('csrf.default_profile', 'default');

        $profile = config("csrf.profiles.{$name}");
        if (!is_array($profile)) {
            throw new \RuntimeException(
                "CSRF profile '{$name}' is not configured. See config/csrf.php."
            );
        }

        return [
            'name'         => $name,
            'methods'      => (array) ($profile['methods'] ?? []),
            'input_name'   => (string) ($profile['input_name'] ?? '_csrf_token'),
            'header_names' => (array) ($profile['header_names'] ?? ['X-CSRF-TOKEN', 'X-XSRF-TOKEN']),
            'except'       => (array) ($profile['except'] ?? []),
        ];
    }

    protected function isReadMethod(Request $request): bool
    {
        return in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    /**
     * Glob-style URI match: `*` matches any run of non-slash characters,
     * `**` matches across slashes. Exact-string entries match too.
     */
    protected function isExcluded(string $uri, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === $uri) {
                return true;
            }
            if (strpos($pattern, '*') !== false && $this->matchGlob($pattern, $uri)) {
                return true;
            }
        }
        return false;
    }

    protected function matchGlob(string $pattern, string $subject): bool
    {
        $regex = preg_quote($pattern, '#');
        // Order matters: replace ** before * so ** doesn't get mangled.
        $regex = str_replace(['\\*\\*', '\\*'], ['.*', '[^/]*'], $regex);
        return (bool) preg_match('#^' . $regex . '$#', $subject);
    }

    protected function getTokenFromRequest(Request $request, array $profile): ?string
    {
        $token = $request->input($profile['input_name']);
        if (is_string($token) && $token !== '') {
            return $token;
        }

        foreach ($profile['header_names'] as $header) {
            $value = $request->header($header);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
