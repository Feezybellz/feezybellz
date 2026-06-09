<?php

namespace App\Middleware;

use Framework\Core\Http\Middleware;
use Framework\Core\Http\Request;
use Framework\Core\Http\Response;

class CsrfMiddleware implements Middleware
{
    /**
     * URIs excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected array $except = [
        '/safe-test',
        '/db-test',
    ];

    public function handle(Request $request, callable $next): Response
    {
        if ($this->isReadMethod($request) || $this->isExcluded($request->uri())) {
            return $next($request);
        }

        $sessionToken = session()->get('_csrf_token');
        $requestToken = $this->getTokenFromRequest($request);

        if (!is_string($sessionToken) || !is_string($requestToken) || $sessionToken === '' || !hash_equals($sessionToken, $requestToken)) {
            if ($request->wantsJson()) {
                return Response::setStatusCode(419)->json([
                    'success' => false,
                    'message' => 'Invalid CSRF token.',
                ]);
            }

            return Response::setStatusCode(419)->html('<h1>419</h1><p>Invalid CSRF token.</p>');
        }

        return $next($request);
    }

    protected function isReadMethod(Request $request): bool
    {
        return in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    protected function isExcluded(string $uri): bool
    {
        return in_array($uri, $this->except, true);
    }

    protected function getTokenFromRequest(Request $request): ?string
    {
        $token = $request->input('_csrf_token');

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $headerToken = $request->header('X-CSRF-TOKEN') ?? $request->header('X-XSRF-TOKEN');

        return is_string($headerToken) ? $headerToken : null;
    }
}
