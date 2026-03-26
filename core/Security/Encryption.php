<?php

namespace Framework\Core\Security;

class Encryption
{
    private static $cipher = 'aes-256-cbc';
    private static $key = null;

    public static function setCipher(string $cipher): void
    {
        if (!in_array(strtolower($cipher), openssl_get_cipher_methods())) {
            throw new \Exception("Encryption Error: Cipher '{$cipher}' is not supported.");
        }
        self::$cipher = $cipher;
    }

    public static function setKey(string $key): void
    {
        self::$key = $key;
    }

    public static function generateHash(string $value): string
    {
        $key = self::resolveKey();
        return hash_hmac('sha256', strtolower(trim($value)), $key);
    }

    public static function encrypt(string $value): string
    {
        $key = self::resolveKey();
        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        $encryptedValue = openssl_encrypt($value, self::$cipher, $key, 0, $iv);

        if ($encryptedValue === false) {
            throw new \Exception('Encryption failed.');
        }

        // Create a MAC to prevent tampering (Padding Oracle Attack protection)
        $mac = self::hash($iv = base64_encode($iv), $encryptedValue, $key);

        $payload = json_encode([
            'iv' => $iv,
            'value' => $encryptedValue,
            'mac' => $mac,
            'cipher' => self::$cipher
        ]);

        return base64_encode($payload);
    }

    public static function decrypt(string $payload): string
    {
        $key = self::resolveKey();
        $decoded = json_decode(base64_decode($payload), true);

        if (!$decoded || !isset($decoded['iv'], $decoded['value'], $decoded['mac'], $decoded['cipher'])) {
            throw new \Exception('Invalid encryption payload.');
        }

        // Verify MAC before decrypting (hash_equals prevents timing attacks)
        $calculatedMac = self::hash($decoded['iv'], $decoded['value'], $key);
        if (!hash_equals($calculatedMac, $decoded['mac'])) {
            throw new \Exception('Encryption MAC is invalid. Payload was tampered with.');
        }

        $iv = base64_decode($decoded['iv']);
        $cipher = $decoded['cipher'];

        $decrypted = openssl_decrypt($decoded['value'], $cipher, $key, 0, $iv);
        
        if ($decrypted === false) {
            throw new \Exception('Decryption failed.');
        }

        return $decrypted;
    }

    /**
     * Generate a keyed hash for the payload.
     */
    private static function hash(string $iv, string $value, string $key): string
    {
        return hash_hmac('sha256', $iv . $value, $key);
    }

    /**
     * Resolve the encryption key from the framework config.
     */
    private static function resolveKey(): string
    {
        // 1. Priority: Use the manually set key, or fallback to the framework config/env
        $key = self::$key ?? (function_exists('config') ? config('app.key') : ($_ENV['APP_KEY'] ?? null));

        if (empty($key)) {
            throw new \Exception('Encryption Error: No application key (APP_KEY) is set in your .env file.');
        }

        // 2. Intelligence: If the key is prefixed with base64:, decode it
        if (strpos($key, 'base64:') === 0) {
            $key = base64_decode(substr($key, 7));
        }

        return $key;
    }
}
