<?php

namespace Framework\Core\Captcha;

use Framework\Core\Captcha\Exceptions\CaptchaException;
use Framework\Core\Http\Middleware;
use Framework\Core\Http\Request;
use Framework\Core\Http\Response;

class CaptchaMiddleware implements Middleware
{
    /**
     * Handle the incoming request and enforce Captcha / PoW verification.
     *
     * Supports specifying an expected form scope name via middleware parameter:
     *   Router::post('/contact', 'captcha:contact_form', [Controller::class, 'send']);
     */
    public function handle(Request $request, callable $next, array $params = []): Response
    {
        // Read-only methods do not require captcha challenge checks
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $expectedName = $params[0] ?? null;

        try {
            Captcha::verifyOrFail($request, $expectedName);
        } catch (CaptchaException $e) {
            if ($request->wantsJson()) {
                return Response::setStatusCode(403)->json([
                    'success' => false,
                    'error' => 'Security challenge verification failed.',
                    'message' => $e->getMessage(),
                ]);
            }

            return Response::setStatusCode(403)->html(
                '<h1>403 Forbidden</h1><p>Security check failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>'
            );
        }

        return $next($request);
    }
}
