<?php

namespace Framework\Core\Push;

use Exception;

class Vapid
{
    /**
     * Generate a new pair of VAPID Public and Private keys.
     *
     * @return array ['publicKey' => '...', 'privateKey' => '...']
     */
    public static function generateKeys(): array
    {
        // 1. Configure OpenSSL strictly for the P-256 Elliptic Curve
        // We remove 'private_key_bits' to prevent OpenSSL 3+ strict length errors.
        $config = [
            "private_key_type" => OPENSSL_KEYTYPE_EC,
            "curve_name"       => "prime256v1"
        ];

        // 2. Generate the Key Pair
        $resource = openssl_pkey_new($config);
        
        if (!$resource) {
            throw new Exception('OpenSSL Error: ' . openssl_error_string());
        }

        // 3. Extract the raw details
        $details = openssl_pkey_get_details($resource);
        
        if (!isset($details['ec'])) {
            throw new Exception('Failed to extract Elliptic Curve details.');
        }

        // 4. Construct the Raw Keys
        // Private Key is the 'd' parameter (32 bytes)
        $privateKeyRaw = $details['ec']['d'];
        
        // Public Key is an uncompressed point: 0x04 + 'x' parameter + 'y' parameter (65 bytes total)
        $publicKeyRaw = chr(0x04) . $details['ec']['x'] . $details['ec']['y'];

        // 5. Return as Base64URL encoded strings (Browser standard)
        return [
            'publicKey'  => self::base64UrlEncode($publicKeyRaw),
            'privateKey' => self::base64UrlEncode($privateKeyRaw),
        ];
    }

    /**
     * Web Push requires Base64-URL encoding (replaces + and / with - and _)
     * and strips the padding (=) at the end.
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}