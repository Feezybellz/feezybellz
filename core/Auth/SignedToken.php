<?php

namespace Framework\Core\Auth;

/**
 * Signed, expiring, tamper-evident payload envelope.
 *
 * Wire format: `<base64url-body>.<hex-hmac>`
 *   body = json_encode(['p' => $payload, 'e' => $expiresAt])
 *   hmac = hash_hmac('sha256', body, APP_KEY)
 *
 * Powers:
 *   - Remember-me cookies (sign an arbitrary payload, store as a cookie)
 *   - Password-reset links (sign a payload, embed in a URL)
 *   - Any other "I need to hand the client something that says X and can't
 *     be tampered with, without keeping server-side state" pattern.
 *
 * ZERO storage. Everything the framework needs is in the token itself,
 * verified with constant-time HMAC. Developer decides what "X" means —
 * framework never introspects the payload.
 */
class SignedToken
{
    /**
     * Sign a payload with an expiry.
     *
     * @param  mixed $payload  Anything JSON-encodable.
     * @param  int   $ttl      Seconds until expiry.
     */
    public static function issue($payload, int $ttl): string
    {
        $body = self::base64UrlEncode(json_encode([
            'p' => $payload,
            'e' => time() + $ttl,
        ]));
        $sig = hash_hmac('sha256', $body, self::key());
        return $body . '.' . $sig;
    }

    /**
     * Verify a token, checking both the signature (constant-time) AND
     * the expiry. Returns the original payload or null on any failure.
     *
     * @return mixed
     */
    public static function verify(string $token)
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$body, $sig] = $parts;

        $expected = hash_hmac('sha256', $body, self::key());
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $decoded = json_decode(self::base64UrlDecode($body), true);
        if (!is_array($decoded) || !isset($decoded['p'], $decoded['e'])) {
            return null;
        }
        if ((int) $decoded['e'] < time()) {
            return null;
        }
        return $decoded['p'];
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $data = strtr($data, '-_', '+/');
        $pad = (4 - strlen($data) % 4) % 4;
        return base64_decode($data . str_repeat('=', $pad));
    }

    /**
     * The signing key — reuses APP_KEY. No separate secret to manage.
     * A missing APP_KEY throws with a clear message instead of silently
     * signing with the empty string.
     */
    private static function key(): string
    {
        $k = function_exists('config') ? config('app.key') : null;
        $k = $k ?: ($_ENV['APP_KEY'] ?? null);
        if (empty($k)) {
            throw new \RuntimeException(
                "SignedToken: APP_KEY is not configured. Run "
                . "`php console make:env --generate-key --force`."
            );
        }
        if (strpos($k, 'base64:') === 0) {
            $k = base64_decode(substr($k, 7));
        }
        return $k;
    }
}
