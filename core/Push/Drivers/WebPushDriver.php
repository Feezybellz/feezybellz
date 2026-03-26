<?php

namespace Framework\Core\Push\Drivers;

use Framework\Core\Push\PushDriverInterface;
use Framework\Core\Push\WebPushEncryptor;
use Framework\Core\Logging\Log;
use Exception;

/**
 * Web Push driver.
 *
 * Supports two delivery paths automatically:
 *
 *  1. Legacy FCM  — endpoint contains "google.com/fcm/send/"
 *                   Uses your FCM Server Key (no encryption needed).
 *
 *  2. Modern VAPID — all other endpoints (Chrome 120+, Firefox, Edge, Safari)
 *                    Encrypts payload with RFC 8291 + signs a VAPID JWT.
 *
 * Config keys expected (push.web):
 *   public_key        — VAPID public key  (Base64URL, from php console push:generate)
 *   private_key       — VAPID private key (Base64URL, from php console push:generate)
 *   subject           — mailto: or https: identifier for VAPID JWT
 *   legacy_server_key — FCM Server Key from Firebase Console (only needed for old endpoints)
 */
class WebPushDriver implements PushDriverInterface
{
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public Interface
    // ─────────────────────────────────────────────────────────────────────────

    public function send($subscriptionJson, string $title, string $body, array $data = []): bool
    {
        $subscription = is_array($subscriptionJson)
            ? $subscriptionJson
            : json_decode($subscriptionJson, true);

        if (!isset($subscription['endpoint'], $subscription['keys']['p256dh'], $subscription['keys']['auth'])) {
            throw new Exception("WebPush: Invalid subscription object. Missing endpoint or keys.");
        }

        // Route to the correct delivery path based on endpoint URL
        if ($this->isLegacyFcmEndpoint($subscription['endpoint'])) {
            return $this->sendViaLegacyFcm($subscription, $title, $body, $data);
        }

        return $this->sendViaVapid($subscription, $title, $body, $data);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Path 1: Legacy FCM (old Chrome endpoints — google.com/fcm/send/...)
    // ─────────────────────────────────────────────────────────────────────────

    private function isLegacyFcmEndpoint(string $endpoint): bool
    {
        // Modern FCM endpoints (fcm.googleapis.com/fcm/send/...) actually support VAPID.
        // We only want to treat it as legacy if it's the OLD format that REQUIRES the server key.
        // Usually, if the endpoint contains /fcm/send/ but is NOT followed by a VAPID-style token, 
        // but modern ones do too. 
        
        // Let's refine: Only use legacy if the user explicitly provided a legacy key AND it matches the old URL pattern.
        $hasLegacyKey = !empty($this->config['legacy_server_key'] ?? '');
        
        return $hasLegacyKey && (
            strpos($endpoint, 'android.googleapis.com/gcm/send') !== false ||
            strpos($endpoint, 'fcm.googleapis.com/fcm/send/') !== false
        );
    }

    private function sendViaLegacyFcm(array $subscription, string $title, string $body, array $data): bool
    {
        $serverKey = $this->config['legacy_server_key'] ?? '';

        if (empty($serverKey)) {
            throw new Exception(
                "WebPush: Legacy FCM endpoint detected but 'legacy_server_key' is not set in push.web config. " .
                "Get it from Firebase Console → Project Settings → Cloud Messaging."
            );
        }

        // Extract the FCM registration token from the endpoint URL
        $token = substr($subscription['endpoint'], strrpos($subscription['endpoint'], '/') + 1);

        $payload = [
            'registration_ids' => [$token],
            'notification'     => [
                'title' => $title,
                'body'  => $body,
            ],
            'data' => $data,
        ];

        $response = $this->httpPost(
            'https://fcm.googleapis.com/fcm/send',
            ['Authorization: key=' . $serverKey, 'Content-Type: application/json'],
            json_encode($payload)
        );

        // FCM legacy API returns 200 on success
        return $response['status'] === 200;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Path 2: Modern VAPID (Chrome 120+, Firefox, Edge, Safari)
    // ─────────────────────────────────────────────────────────────────────────

    private function sendViaVapid(array $subscription, string $title, string $body, array $data): bool
    {
        $endpoint = $subscription['endpoint'];
        $parsedUrl = parse_url($endpoint);
        $audience = ($parsedUrl['scheme'] ?? 'https') . '://' . ($parsedUrl['host'] ?? 'localhost');

        // 1. Build and sign the VAPID JWT
        $jwt = $this->buildVapidJwt($audience);

        // 2. Encrypt the payload (RFC 8291)
        $encryptedBody = WebPushEncryptor::encrypt(
            json_encode(['title' => $title, 'body' => $body, 'data' => $data]),
            $subscription['keys']['p256dh'],
            $subscription['keys']['auth']
        );

        // 3. Send
        $headers = [
            'Authorization: vapid t=' . $jwt . ', k=' . $this->config['public_key'],
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 86400',
        ];

        $response = $this->httpPost($endpoint, $headers, $encryptedBody);

        // Web Push standard returns 201 Created on success
        return $response['status'] === 201;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VAPID JWT (ES256)
    // ─────────────────────────────────────────────────────────────────────────

    private function buildVapidJwt(string $audience): string
    {
        $header  = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = self::base64UrlEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => $this->config['subject'] ?? 'mailto:admin@localhost',
        ]));

        $unsignedToken = $header . '.' . $payload;

        $privateKey = $this->loadVapidPrivateKey($this->config['private_key']);

        openssl_sign($unsignedToken, $derSignature, $privateKey, OPENSSL_ALGO_SHA256);

        return $unsignedToken . '.' . self::base64UrlEncode(self::derSignatureToRaw($derSignature));
    }

    private function loadVapidPrivateKey(string $base64UrlKey)
    {
        $rawKey = self::base64UrlDecode($base64UrlKey);

        // Ensure exactly 32 bytes (pad if needed)
        $rawKey = str_pad($rawKey, 32, "\x00", STR_PAD_LEFT);

        // Wrap in PKCS#8 DER structure for P-256
        $der = "\x30\x41"               // SEQUENCE (65 bytes)
             . "\x02\x01\x00"           // INTEGER 0 (version)
             . "\x30\x13"               // SEQUENCE (AlgorithmIdentifier)
             . "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"    // OID ecPublicKey
             . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07" // OID prime256v1
             . "\x04\x27"               // OCTET STRING (39 bytes)
             . "\x30\x25"               // SEQUENCE (37 bytes)
             . "\x02\x01\x01"           // INTEGER 1 (version)
             . "\x04\x20"               // OCTET STRING (32 bytes)
             . $rawKey;                 // The actual private key bytes

        $pem = "-----BEGIN PRIVATE KEY-----\n"
             . chunk_split(base64_encode($der), 64, "\n")
             . "-----END PRIVATE KEY-----\n";

        $key = openssl_pkey_get_private($pem);

        if (!$key) {
            $errors = [];
            while ($msg = openssl_error_string()) {
                $errors[] = $msg;
            }
            throw new Exception("WebPush: Invalid VAPID private key. OpenSSL: " . implode(', ', $errors));
        }

        return $key;
    }

    /**
     * Convert OpenSSL DER-encoded signature to raw 64-byte R||S format for JWT.
     */
    private static function derSignatureToRaw(string $der): string
    {
        $hex     = bin2hex($der);
        $rLen    = hexdec(substr($hex, 6, 2)) * 2;
        $r       = substr($hex, 8, $rLen);
        $sOffset = 8 + $rLen + 4;
        $sLen    = hexdec(substr($hex, $sOffset - 2, 2)) * 2;
        $s       = substr($hex, $sOffset, $sLen);

        // Trim leading zero padding (DER uses it for sign, JWT does not)
        if (strlen($r) > 64) $r = substr($r, -64);
        if (strlen($s) > 64) $s = substr($s, -64);

        return hex2bin(
            str_pad($r, 64, '0', STR_PAD_LEFT) .
            str_pad($s, 64, '0', STR_PAD_LEFT)
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HTTP Helper
    // ─────────────────────────────────────────────────────────────────────────

    private function httpPost(string $url, array $headers, string $body): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("WebPush: cURL error — " . $error);
        }

        return ['status' => $status, 'body' => $response];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Encoding Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $padded = strtr($data, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        return base64_decode($padded);
    }
}
