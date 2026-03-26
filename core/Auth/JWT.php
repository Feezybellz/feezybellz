<?php

namespace Framework\Core\Auth;

class JWT
{

    private static $secret;
    public static function setSecret($secret)
    {
        self::$secret = $secret;
    }
    /**
     * Encode a payload into a JWT token
     *
     * @param array $payload
     * @param string $secret
     * @param int $expiration Time in seconds (default: 1 hour)
     * @param string $algorithm
     * @return string
     */
    public static function encode(array $payload, string $secret = null, $expiration = 3600, string $algorithm = 'HS256'): string
    {
        $secret = $secret ?? self::$secret ?? config('app.jwt_secret') ?? env('JWT_SECRET');
        $header = [
            'typ' => 'JWT',
            'alg' => $algorithm
        ];

        // Add issued at and expiration time
        $issuedAt = time();
        $payload['iat'] = $issuedAt;
        $payload['exp'] = $issuedAt + $expiration;

        // Encode header and payload
        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        // Create signature
        $signature = self::sign($headerEncoded . '.' . $payloadEncoded, $secret, $algorithm);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }

    /**
     * Decode and verify a JWT token
     *
     * @param string $token
     * @param string $secret
     * @return array|null Returns payload if valid, null if invalid
     */
    public static function decode(string $token, string $secret = null): ?array
    {
        $secret = $secret ?? self::$secret ?? config('app.jwt_secret') ?? env('JWT_SECRET');
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Verify signature
        $expectedSignature = self::sign($headerEncoded . '.' . $payloadEncoded, $secret);
        
        if (!hash_equals($expectedSignature, $signatureEncoded)) {
            return null; // Invalid signature
        }

        // Decode payload
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);

        if (!$payload) {
            return null;
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null; // Token expired
        }

        return $payload;
    }

    /**
     * Verify a JWT token without decoding
     *
     * @param string $token
     * @param string $secret
     * @return bool
     */
    public static function verify(string $token, string $secret): bool
    {
        return self::decode($token, $secret) !== null;
    }

    /**
     * Get payload from token without verification (use with caution)
     *
     * @param string $token
     * @return array|null
     */
    public static function getPayload(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($parts[1]), true);

        return $payload ?: null;
    }

    /**
     * Check if token is expired
     *
     * @param string $token
     * @return bool
     */
    public static function isExpired(string $token): bool
    {
        $payload = self::getPayload($token);

        if (!$payload || !isset($payload['exp'])) {
            return true;
        }

        return $payload['exp'] < time();
    }

    /**
     * Create signature
     *
     * @param string $data
     * @param string $secret
     * @param string $algorithm
     * @return string
     */
    private static function sign(string $data, string $secret = null, string $algorithm = 'HS256'): string
    {
        $secret = $secret ?? self::$secret ?? config('app.jwt_secret') ?? env('JWT_SECRET');
        switch ($algorithm) {
            case 'HS256':
                $hashAlgo = 'sha256';
                break;
            case 'HS384':
                $hashAlgo = 'sha384';
                break;
            case 'HS512':
                $hashAlgo = 'sha512';
                break;
            default:
                $hashAlgo = 'sha256';
        }

        $signature = hash_hmac($hashAlgo, $data, $secret, true);

        return self::base64UrlEncode($signature);
    }

    /**
     * Base64 URL encode
     *
     * @param string $data
     * @return string
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     *
     * @param string $data
     * @return string
     */
    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Refresh a token (create new token with extended expiration)
     *
     * @param string $token
     * @param string $secret
     * @param int $expiration
     * @return string|null
     */
    public static function refresh(string $token, string $secret = null, int $expiration = 3600): ?string
    {
        $secret = $secret ?? self::$secret ?? config('app.jwt_secret') ?? env('JWT_SECRET');
        $payload = self::decode($token, $secret);

        if (!$payload) {
            return null;
        }

        // Remove old timestamps
        unset($payload['iat'], $payload['exp']);

        // Create new token
        return self::encode($payload, $secret, $expiration);
    }
}
