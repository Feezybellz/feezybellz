<?php

namespace Framework\Core\Auth;

/**
 * JSON Web Token (HMAC-SHA family only).
 *
 * Verification rules:
 *  - Algorithm is locked to the server-configured value (config('jwt.algorithm')
 *    or whatever setAlgorithm() was last called with). The `alg` header on the
 *    incoming token is **ignored**. This blocks "alg: none" and HS/RS confusion.
 *  - Signature comparison uses hash_equals (constant time).
 *  - `exp` is required and must be in the future (with configured leeway).
 *  - `nbf` if present must be in the past (with leeway).
 *  - `iss` and `aud` are checked against the configured values when those
 *    config values are non-empty. `aud` may be a string or array on the token.
 *
 * Key rotation:
 *  - Configure `jwt.secret` as an array. The first entry signs new tokens;
 *    every entry is tried for verification, so tokens minted under an older
 *    key keep working until they expire. Promote the new key, leave the old
 *    one in second slot for one TTL period, then drop it.
 */
class JWT
{
    private static $secret = null;
    private static ?string $algorithm = null;

    private const SUPPORTED_ALGORITHMS = [
        'HS256' => 'sha256',
        'HS384' => 'sha384',
        'HS512' => 'sha512',
    ];

    public static function setSecret($secret): void
    {
        // Accept a scalar or an array. Verification loops over the array.
        self::$secret = $secret;
    }

    public static function setAlgorithm(string $algorithm): void
    {
        if (!isset(self::SUPPORTED_ALGORITHMS[$algorithm])) {
            throw new \InvalidArgumentException("Unsupported JWT algorithm: {$algorithm}");
        }
        self::$algorithm = $algorithm;
    }

    /**
     * Encode a payload into a signed JWT.
     *
     * The `iat` and `exp` claims are stamped automatically. To add `nbf`,
     * `iss`, `aud`, `jti`, etc., put them in $payload — they'll be preserved.
     */
    public static function encode(array $payload, $secret = null, int $expiration = 3600, ?string $algorithm = null): string
    {
        $algorithm = $algorithm ?? self::resolveAlgorithm();
        $signingKey = self::primarySecret($secret);

        $header = ['typ' => 'JWT', 'alg' => $algorithm];

        $issuedAt = time();
        $payload['iat'] = $issuedAt;
        $payload['exp'] = $issuedAt + $expiration;

        // Stamp iss/aud from config if the caller didn't supply them.
        $cfgIss = self::cfg('issuer');
        $cfgAud = self::cfg('audience');
        if ($cfgIss !== '' && !isset($payload['iss'])) {
            $payload['iss'] = $cfgIss;
        }
        if ($cfgAud !== '' && !isset($payload['aud'])) {
            $payload['aud'] = $cfgAud;
        }

        $headerEncoded  = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));
        $signature      = self::sign($headerEncoded . '.' . $payloadEncoded, $signingKey, $algorithm);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }

    /**
     * Verify a JWT and return its payload, or null on any failure.
     *
     * Failure modes (all return null, never throw):
     *   - Malformed token (wrong number of segments, bad base64, bad JSON).
     *   - Signature doesn't match any configured key.
     *   - Claims fail validation (exp/nbf/iss/aud).
     */
    public static function decode(string $token, $secret = null): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        $algorithm = self::resolveAlgorithm();
        $signingInput = $headerEncoded . '.' . $payloadEncoded;

        // Try every configured secret (key rotation). hash_equals on every
        // attempt so we never short-circuit and leak which key matched.
        $secrets = self::secretsList($secret);
        $matched = false;
        foreach ($secrets as $candidate) {
            $expected = self::sign($signingInput, $candidate, $algorithm);
            if (hash_equals($expected, $signatureEncoded)) {
                $matched = true;
            }
        }
        if (!$matched) {
            return null;
        }

        $payloadJson = self::base64UrlDecode($payloadEncoded);
        if ($payloadJson === false || $payloadJson === '') {
            return null;
        }
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        if (!self::validateClaims($payload)) {
            return null;
        }

        return $payload;
    }

    public static function verify(string $token, $secret = null): bool
    {
        return self::decode($token, $secret) !== null;
    }

    /**
     * Decode without verifying. Use only when you genuinely don't need
     * authenticity (e.g. inspecting an already-failed token in a log).
     */
    public static function getPayload(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        $json = self::base64UrlDecode($parts[1]);
        $payload = json_decode((string) $json, true);
        return is_array($payload) ? $payload : null;
    }

    public static function isExpired(string $token): bool
    {
        $payload = self::getPayload($token);
        if (!$payload || !isset($payload['exp'])) {
            return true;
        }
        return ((int) $payload['exp']) < time();
    }

    /**
     * Verify the incoming token, drop iat/exp/nbf, mint a fresh one.
     * Returns null if the incoming token was invalid (e.g. expired without
     * leeway, bad signature) — caller must treat that as a re-login event.
     */
    public static function refresh(string $token, $secret = null, int $expiration = 3600): ?string
    {
        $payload = self::decode($token, $secret);
        if (!$payload) {
            return null;
        }

        unset($payload['iat'], $payload['exp'], $payload['nbf']);
        return self::encode($payload, $secret, $expiration);
    }

    /**
     * Validate exp/nbf/iss/aud with configured leeway.
     */
    private static function validateClaims(array $payload): bool
    {
        $now = time();
        $leeway = (int) self::cfg('leeway', 30);

        if (!isset($payload['exp']) || ((int) $payload['exp'] + $leeway) < $now) {
            return false;
        }
        if (isset($payload['nbf']) && ((int) $payload['nbf'] - $leeway) > $now) {
            return false;
        }
        if (isset($payload['iat']) && ((int) $payload['iat'] - $leeway) > $now) {
            return false;
        }

        $cfgIss = self::cfg('issuer');
        if ($cfgIss !== '' && (($payload['iss'] ?? null) !== $cfgIss)) {
            return false;
        }

        $cfgAud = self::cfg('audience');
        if ($cfgAud !== '') {
            $aud = $payload['aud'] ?? null;
            $audMatches = is_array($aud) ? in_array($cfgAud, $aud, true) : ($aud === $cfgAud);
            if (!$audMatches) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sign data using the configured algorithm.
     */
    private static function sign(string $data, string $secret, string $algorithm): string
    {
        $hashAlgo = self::SUPPORTED_ALGORITHMS[$algorithm] ?? null;
        if ($hashAlgo === null) {
            throw new \InvalidArgumentException("Unsupported JWT algorithm: {$algorithm}");
        }
        return self::base64UrlEncode(hash_hmac($hashAlgo, $data, $secret, true));
    }

    private static function resolveAlgorithm(): string
    {
        $algorithm = self::$algorithm ?? self::cfg('algorithm', 'HS256');
        if (!isset(self::SUPPORTED_ALGORITHMS[$algorithm])) {
            throw new \InvalidArgumentException("Unsupported JWT algorithm: {$algorithm}");
        }
        return $algorithm;
    }

    /**
     * The single secret used to sign NEW tokens. First entry wins when an
     * array is configured for rotation.
     */
    private static function primarySecret($override): string
    {
        if (is_string($override) && $override !== '') {
            return $override;
        }
        $secrets = self::secretsList(null);
        if (empty($secrets)) {
            throw new \RuntimeException("No JWT secret configured. Set config('jwt.secret') or env('JWT_SECRET').");
        }
        return $secrets[0];
    }

    /**
     * Every secret to TRY during verification. Caller override → setSecret() →
     * config('jwt.secret') → env('JWT_SECRET'). Strings are wrapped in a single-
     * element array; arrays are returned as-is.
     *
     * @return string[]
     */
    private static function secretsList($override): array
    {
        $source = $override ?? self::$secret ?? (function_exists('config') ? config('jwt.secret') : null) ?? ($_ENV['JWT_SECRET'] ?? null);
        if ($source === null) {
            return [];
        }
        $secrets = is_array($source) ? $source : [$source];
        return array_values(array_filter($secrets, fn($s) => is_string($s) && $s !== ''));
    }

    private static function cfg(string $key, $default = '')
    {
        if (function_exists('config')) {
            $value = config('jwt.' . $key);
            if ($value !== null) {
                return $value;
            }
        }
        return $default;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
