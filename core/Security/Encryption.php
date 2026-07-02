<?php

namespace Framework\Core\Security;

/**
 * Symmetric encryption with authenticated tampering protection.
 *
 * Threat model:
 *  - Confidentiality + integrity for short payloads (cookies, signed URLs,
 *    pre-shared identifiers).
 *  - NOT a general-purpose KMS. Long-lived secrets should be in a vault.
 *
 * Wire format (base64-encoded JSON envelope):
 *
 *   { "v":2, "iv":"<b64>", "value":"<b64-ciphertext>", "mac":"<hex>",
 *     "cipher":"aes-256-cbc" }
 *
 * Notes on the format:
 *  - The MAC is computed over `v.cipher.iv.value` so the cipher field cannot
 *    be silently downgraded by an attacker who got the key elsewhere.
 *  - decrypt() requires the payload's `cipher` to equal the server-configured
 *    cipher (self::$cipher). It does NOT honor whatever the payload says.
 *    The field stays in the envelope only so future migrations can read v1
 *    legacy payloads if needed.
 *  - v=1 (older) payloads had no version field and the MAC didn't cover the
 *    cipher. They're rejected by default; flip the legacy switch with care.
 */
class Encryption
{
    private static $cipher = 'aes-256-cbc';
    private static $key = null;
    private const VERSION = 2;

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

        $ivB64 = base64_encode($iv);
        $mac = self::hash(self::VERSION, self::$cipher, $ivB64, $encryptedValue, $key);

        $payload = json_encode([
            'v'      => self::VERSION,
            'iv'     => $ivB64,
            'value'  => $encryptedValue,
            'mac'    => $mac,
            'cipher' => self::$cipher,
        ]);

        return base64_encode($payload);
    }

    public static function decrypt(string $payload): string
    {
        $key = self::resolveKey();
        $decoded = json_decode(base64_decode($payload), true);

        if (!is_array($decoded)
            || !isset($decoded['iv'], $decoded['value'], $decoded['mac'], $decoded['cipher'])) {
            throw new \Exception('Invalid encryption payload.');
        }

        $version = (int) ($decoded['v'] ?? 1);
        if ($version !== self::VERSION) {
            // We refuse legacy payloads by default. To migrate, decrypt with a
            // version-aware helper and re-encrypt at rest.
            throw new \Exception(
                "Encryption payload version {$version} is not supported by this version of the framework."
            );
        }

        // Lock the cipher to the server-configured one. The payload field is
        // only used to detect tampering / accidental mix-ups.
        if (strtolower($decoded['cipher']) !== strtolower(self::$cipher)) {
            throw new \Exception(
                "Encryption payload cipher mismatch: server is configured for '"
                . self::$cipher . "', payload claims '" . $decoded['cipher'] . "'."
            );
        }

        // Verify MAC before decrypting (covers v, cipher, iv, value).
        $calculatedMac = self::hash($version, $decoded['cipher'], $decoded['iv'], $decoded['value'], $key);
        if (!hash_equals($calculatedMac, $decoded['mac'])) {
            throw new \Exception('Encryption MAC is invalid. Payload was tampered with.');
        }

        $iv = base64_decode($decoded['iv']);
        $decrypted = openssl_decrypt($decoded['value'], self::$cipher, $key, 0, $iv);

        if ($decrypted === false) {
            throw new \Exception('Decryption failed.');
        }

        return $decrypted;
    }

    /**
     * Compute the authentication tag. Inputs are dot-separated and HMAC'd so
     * an attacker cannot transplant fields between payloads or downgrade the
     * cipher without invalidating the tag.
     */
    private static function hash(int $version, string $cipher, string $iv, string $value, string $key): string
    {
        return hash_hmac('sha256', $version . '|' . strtolower($cipher) . '|' . $iv . '|' . $value, $key);
    }

    /**
     * Resolve the encryption key from setKey(), config('app.key'), or env('APP_KEY').
     * Accepts the `base64:` prefix used by `php console make:env --generate-key` output.
     *
     * @throws \RuntimeException if no key is configured. This surfaces early
     * with a clear message instead of a downstream openssl_encrypt failure.
     */
    private static function resolveKey(): string
    {
        $key = self::$key
            ?? (function_exists('config') ? config('app.key') : null)
            ?? ($_ENV['APP_KEY'] ?? null);

        if (empty($key)) {
            throw new \RuntimeException(
                "APP_KEY is not configured. Generate one with:\n"
                . "  php console make:env --generate-key --force\n"
                . "or set APP_KEY=base64:... in your .env manually. The framework\n"
                . "refuses to encrypt with an empty key."
            );
        }

        if (strpos($key, 'base64:') === 0) {
            $key = base64_decode(substr($key, 7));
        }
        return $key;
    }
}
