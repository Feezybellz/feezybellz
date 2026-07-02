<?php

namespace Framework\Core\Http\Middleware;

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;

/**
 * Security headers middleware (opt-in).
 *
 *   Router::middleware('security', function () { ... });
 *   Router::middleware('security:strict', function () { ... });
 *   Router::get('/widget', 'security:none', $handler);
 *
 * Each profile in config/security_headers.php is a flat map of
 * header-name => header-value strings. The middleware loops over the profile
 * and emits each entry as a response header — no computed behavior, no
 * built-in defaults. Whatever you put in the profile is what gets sent.
 *
 * Values of `null` are skipped, so a profile can explicitly *suppress* a
 * header that a parent context might otherwise set.
 *
 * The "raw" use case is intrinsic: add any header name, any string value,
 * and it ships verbatim. There is no allowlist of header names.
 */
class SecurityHeadersMiddleware
{
    public function handle(Request $request, callable $next, array $params = []): Response
    {
        $profile = $this->resolveProfile($params);

        /** @var Response $response */
        $response = $next($request);

        if ($response instanceof Response) {
            foreach ($profile as $name => $value) {
                if ($value === null) {
                    continue; // explicit suppression
                }
                $response->header((string) $name, (string) $value);
            }
        }

        return $response;
    }

    /**
     * Resolve the profile from middleware params or fall back to config default.
     * Profile is always an array of headers — empty array is a valid no-op.
     */
    protected function resolveProfile(array $params): array
    {
        $name = $params[0] ?? config('security_headers.default_profile', 'default');

        $profile = config("security_headers.profiles.{$name}");
        if (!is_array($profile)) {
            throw new \RuntimeException(
                "Security headers profile '{$name}' is not configured. See config/security_headers.php."
            );
        }

        return $profile;
    }
}
