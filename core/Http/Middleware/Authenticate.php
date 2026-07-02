<?php

namespace Framework\Core\Http\Middleware;

use Framework\Core\Auth\Auth;
use Framework\Core\Http\Middleware;
use Framework\Core\Http\Request;
use Framework\Core\Http\Response;

/**
 * `auth` middleware alias.
 *
 * Usage:
 *   Router::middleware('auth', $callback)          // default guard
 *   Router::middleware('auth:api', $callback)      // named guard
 *   Router::get('/dashboard', 'auth', $handler)    // per-route
 *
 * On failure:
 *   - Request wants JSON     → 401 with JSON body
 *   - Request wants HTML     → 302 redirect to config('auth.login_url')
 */
class Authenticate implements Middleware
{
    public function handle(Request $request, callable $next, array $params = []): Response
    {
        $guardName = $params[0] ?? null;

        if (Auth::guard($guardName)->check()) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return Response::setStatusCode(401)->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
        }

        $loginUrl = function_exists('config')
            ? (config('auth.login_url') ?? '/login')
            : '/login';

        return Response::redirect((string) $loginUrl);
    }
}
