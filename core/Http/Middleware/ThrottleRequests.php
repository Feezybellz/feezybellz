<?php

namespace Framework\Core\Http\Middleware;

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use Framework\Core\Routing\RateLimiter;

class ThrottleRequests
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @param \Closure $next
     * @param array $params Contains maxAttempts and decayMinutes from the route definition
     * @return Response
     */
    public function handle(Request $request, \Closure $next, array $params = []): Response
    {
        // Default to 60 requests per 1 minute
        $maxAttempts = isset($params[0]) ? (int) $params[0] : 60;
        $decayMinutes = isset($params[1]) ? (int) $params[1] : 1;
        $decaySeconds = $decayMinutes * 60;

        $key = $this->resolveRequestSignature($request);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return $this->buildResponse($key, $maxAttempts);
        }

        RateLimiter::hit($key, $decaySeconds);

        /** @var Response $response */
        $response = $next($request);

        return $this->addHeaders(
            $response, $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    /**
     * Resolve the request signature.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        return sha1(implode('|', [
            $request->method(),
            $request->uri(),
            $request->ip()
        ]));
    }

    /**
     * Build the response when too many requests have been made.
     */
    protected function buildResponse(string $key, int $maxAttempts): Response
    {
        $retryAfter = RateLimiter::availableIn($key);

        $response = new Response();
        $response->setStatusCode(429);
        $response->setContent('Too Many Requests');

        $response->setHeader('Retry-After', $retryAfter);
        $response->setHeader('X-RateLimit-Limit', $maxAttempts);
        $response->setHeader('X-RateLimit-Remaining', 0);

        return $response;
    }

    /**
     * Add the limit header information to the given response.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        $response->setHeader('X-RateLimit-Limit', $maxAttempts);
        $response->setHeader('X-RateLimit-Remaining', $remainingAttempts);

        return $response;
    }

    /**
     * Calculate the number of remaining attempts.
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        $attempts = RateLimiter::attempts($key);
        return max(0, $maxAttempts - $attempts);
    }
}
