<?php

namespace Framework\Core\Push;

use Exception;

/**
 * Encrypts Web Push payloads using AES-128-GCM with ECDH key agreement.
 * Fully compliant with RFC 8291 (Message Encryption for Web Push).
 */
class WebPushEncryptor
{
    private const CURVE      = 'prime256v1';
    private const HASH       = 'sha256';
    private const CEK_LENGTH = 16; // AES-128
    private const TAG_LENGTH = 16; // GCM auth tag
    private const NONCE_LEN  = 12;
    private const SALT_LEN   = 16;
    private const RS         = 4096; // Record size

    /**
     * Encrypt a plaintext payload for delivery to a browser push subscription.
     *
     * @param  string $payload           Raw JSON string to encrypt
     * @param  string $browserPublicKey  Base64URL-encoded p256dh key from subscription
     * @param  string $browserAuthSecret Base64URL-encoded auth secret from subscription
     * @return string Binary encrypted body ready to POST to the push endpoint
     */
    public static function encrypt(string $payload, string $browserPublicKey, string $browserAuthSecret): string
    {
        // ── 1. Decode browser keys ───────────────────────────────────────────
        $receiverPublicKeyRaw = self::base64UrlDecode($browserPublicKey);
        $authSecret           = self::base64UrlDecode($browserAuthSecret);

        // ── 2. Generate ephemeral sender key pair ────────────────────────────
        $senderKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => self::CURVE,
        ]);

        if (!$senderKey) {
            throw new Exception('Failed to generate ephemeral key pair: ' . openssl_error_string());
        }

        $senderDetails       = openssl_pkey_get_details($senderKey);
        $senderPublicKeyRaw  = chr(0x04) . $senderDetails['ec']['x'] . $senderDetails['ec']['y'];

        // ── 3. ECDH shared secret ────────────────────────────────────────────
        $receiverPublicKeyPem = self::rawPublicKeyToPem($receiverPublicKeyRaw);
        $sharedSecret         = openssl_pkey_derive($receiverPublicKeyPem, $senderKey, 32);

        if ($sharedSecret === false) {
            throw new Exception('ECDH key derivation failed: ' . openssl_error_string());
        }

        // ── 4. Random salt ───────────────────────────────────────────────────
        $salt = random_bytes(self::SALT_LEN);

        // ── 5. HKDF key derivation (RFC 8291) ────────────────────────────────
        // Step 1: Extract PRK using auth secret as HKDF salt
        $ikm = self::hkdfExtract($authSecret, $sharedSecret);

        // Step 2: Build context info
        // "WebPush: info" + 0x00 + receiver_pub + sender_pub
        $info = "WebPush: info\x00" . $receiverPublicKeyRaw . $senderPublicKeyRaw;

        // Step 3: Expand PRK into input keying material
        $inputKeyingMaterial = self::hkdfExpand($ikm, $info, 32);

        // Step 4: Derive CEK (Content Encryption Key) and Nonce from salt
        $prk   = self::hkdfExtract($salt, $inputKeyingMaterial);
        $cek   = self::hkdfExpand($prk, "Content-Encoding: aes128gcm\x00", self::CEK_LENGTH);
        $nonce = self::hkdfExpand($prk, "Content-Encoding: nonce\x00",     self::NONCE_LEN);

        // ── 6. Pad + Encrypt ─────────────────────────────────────────────────
        // RFC 8291: payload must end with 0x02 delimiter byte
        $paddedPayload = $payload . "\x02";

        $tag              = '';
        $encryptedPayload = openssl_encrypt(
            $paddedPayload,
            'aes-128-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($encryptedPayload === false) {
            throw new Exception('AES-128-GCM encryption failed: ' . openssl_error_string());
        }

        // ── 7. Build RFC 8188 binary record ──────────────────────────────────
        // salt (16) + rs (4) + idlen (1) + sender_public_key (65) + ciphertext + tag (16)
        return $salt
            . pack('N', self::RS)                          // Record Size: 4 bytes big-endian
            . pack('C', strlen($senderPublicKeyRaw))       // IDLEN: 1 byte (always 65)
            . $senderPublicKeyRaw                          // Sender public key
            . $encryptedPayload                            // Ciphertext
            . $tag;                                        // GCM auth tag
    }

    // ── HKDF Helpers (RFC 5869) ──────────────────────────────────────────────

    /**
     * HKDF-Extract: PRK = HMAC-Hash(salt, IKM)
     */
    private static function hkdfExtract(string $salt, string $ikm): string
    {
        return hash_hmac(self::HASH, $ikm, $salt, true);
    }

    /**
     * HKDF-Expand: OKM = T(1) where T(1) = HMAC-Hash(PRK, info || 0x01)
     */
    private static function hkdfExpand(string $prk, string $info, int $length): string
    {
        return substr(hash_hmac(self::HASH, $info . "\x01", $prk, true), 0, $length);
    }

    // ── Encoding Helpers ─────────────────────────────────────────────────────

    private static function base64UrlDecode(string $data): string
    {
        $padded = strtr($data, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        return base64_decode($padded);
    }

    /**
     * Wrap a raw 65-byte uncompressed EC public key in a PEM envelope
     * so OpenSSL can use it for ECDH derivation.
     */
    private static function rawPublicKeyToPem(string $rawKey): string
    {
        // SubjectPublicKeyInfo DER header for P-256
        $der = "\x30\x59"           // SEQUENCE
             . "\x30\x13"           // SEQUENCE (AlgorithmIdentifier)
             . "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"   // OID: ecPublicKey
             . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07" // OID: prime256v1
             . "\x03\x42\x00"       // BIT STRING (66 bytes, 0 unused bits)
             . $rawKey;

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }
}