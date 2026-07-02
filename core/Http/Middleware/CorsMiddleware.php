<?php

namespace Framework\Core\Http\Middleware;

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;

/**
 * CORS middleware (opt-in).
 *
 * The framework does NOT register this globally. A developer enables CORS for
 * a route or group by applying the `cors` alias (or `cors:<profile>` for a
 * named profile in config/cors.php).
 *
 *   Router::get('/api/me', 'cors', [MeController::class, 'show']);
 *   Router::middleware('cors:public', function () { ... });
 *
 * Behavior:
 *  - Preflight (OPTIONS) requests get a 204 with the appropriate CORS headers
 *    when the origin is allowed, or a bare 204 with no CORS headers when not.
 *  - Real requests run normally; CORS headers are appended on the way out.
 *  - Origins are matched against an explicit allow-list, or against a single
 *    '*' wildcard. Arbitrary reflection is never used.
 *  - Configuring `allow_credentials => true` together with '*' is rejected
 *    at handler entry — browsers refuse that combination, and reflecting an
 *    arbitrary origin while credentials are enabled is a CSRF amplifier.
 *
 * Config lives in config/cors.php. See that file for profile examples.
 */
class CorsMiddleware
{
    public function handle(Request $request, callable $next, array $params = []): Response
    {
        $profile = $this->resolveProfile($params);

        $origin = $request->header('Origin');
        $isPreflight = $request->method() === 'OPTIONS'
            && $request->header('Access-Control-Request-Method') !== null;

        // Preflight: short-circuit. Never run the underlying handler.
        if ($isPreflight) {
            $response = new Response();
            $response->setStatusCode(204);
            $this->applyHeaders($response, $profile, $origin);
            return $response;
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response instanceof Response) {
            $this->applyHeaders($response, $profile, $origin);
        }

        return $response;
    }

    /**
     * Resolve the configured profile, validating it before use. Profile name
     * comes from the first middleware parameter (`cors:<name>`), or falls back
     * to the `default_profile` config key.
     */
    protected function resolveProfile(array $params): array
    {
        $name = $params[0] ?? config('cors.default_profile', 'default');

        $profile = config("cors.profiles.{$name}");
        if (!is_array($profile)) {
            throw new \RuntimeException(
                "CORS profile '{$name}' is not configured. See config/cors.php."
            );
        }

        $allowCredentials = (bool) ($profile['allow_credentials'] ?? false);
        $allowedOrigins = (array) ($profile['allowed_origins'] ?? []);

        if ($allowCredentials && in_array('*', $allowedOrigins, true)) {
            throw new \RuntimeException(
                "CORS profile '{$name}' has allow_credentials=true with a wildcard origin. "
                . "Browsers reject this combination — list specific origins instead."
            );
        }

        return [
            'name'              => $name,
            'allowed_origins'   => $allowedOrigins,
            'allowed_methods'   => (array) ($profile['allowed_methods'] ?? []),
            'allowed_headers'   => (array) ($profile['allowed_headers'] ?? []),
            'exposed_headers'   => (array) ($profile['exposed_headers'] ?? []),
            'max_age'           => (int)   ($profile['max_age'] ?? 86400),
            'allow_credentials' => $allowCredentials,
        ];
    }

    protected function applyHeaders(Response $response, array $profile, ?string $origin): void
    {
        $matched = $this->matchOrigin($origin, $profile['allowed_origins']);

        // No allowed origin → emit nothing. The browser will block the call.
        if ($matched === null) {
            return;
        }

        $response->header('Access-Control-Allow-Origin', $matched);

        if ($matched !== '*') {
            // Origin-dependent caching: tell caches not to mix responses
            // intended for different origins.
            $response->header('Vary', 'Origin');
        }

        if (!empty($profile['allowed_methods'])) {
            $response->header('Access-Control-Allow-Methods', implode(', ', $profile['allowed_methods']));
        }
        if (!empty($profile['allowed_headers'])) {
            $response->header('Access-Control-Allow-Headers', implode(', ', $profile['allowed_headers']));
        }
        if (!empty($profile['exposed_headers'])) {
            $response->header('Access-Control-Expose-Headers', implode(', ', $profile['exposed_headers']));
        }
        if ($profile['max_age'] > 0) {
            $response->header('Access-Control-Max-Age', (string) $profile['max_age']);
        }
        if ($profile['allow_credentials']) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }
    }

    /**
     * Return the value to echo back in Access-Control-Allow-Origin, or null
     * to omit the header entirely. Matching rules:
     *   - allowed_origins contains '*'  → return '*' (only when no credentials).
     *   - exact string match           → return the matched origin.
     *   - no match                     → null.
     */
    protected function matchOrigin(?string $origin, array $allowedOrigins): ?string
    {
        if (empty($allowedOrigins)) {
            return null;
        }
        if (in_array('*', $allowedOrigins, true)) {
            return '*';
        }
        if ($origin === null || $origin === '') {
            return null;
        }
        return in_array($origin, $allowedOrigins, true) ? $origin : null;
    }
}
