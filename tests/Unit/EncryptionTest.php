<?php

namespace Tests\Unit;

use Framework\Core\Security\Encryption;
use Framework\Core\Testing\TestCase;

/**
 * Converted from the ad-hoc encryption verifier (claude_fix.md).
 */
class EncryptionTest extends TestCase
{
    // Pure crypto — no framework bootstrap needed.
    protected function setUp(): void
    {
        Encryption::setKey('0123456789abcdef0123456789abcdef'); // 32 bytes for aes-256
    }

    public function test_encrypt_then_decrypt_round_trips(): void
    {
        $plain = 'the quick brown fox';
        $cipher = Encryption::encrypt($plain);

        $this->assertNotEquals($plain, $cipher);
        $this->assertSame($plain, Encryption::decrypt($cipher));
    }

    public function test_ciphertext_is_non_deterministic(): void
    {
        // Random IV per call means the same plaintext encrypts differently.
        $this->assertNotEquals(
            Encryption::encrypt('same'),
            Encryption::encrypt('same')
        );
    }

    public function test_tampered_payload_is_rejected(): void
    {
        $cipher = Encryption::encrypt('sensitive');
        $decoded = json_decode(base64_decode($cipher), true);
        $decoded['value'] = base64_encode('tampered-ciphertext');
        $tampered = base64_encode(json_encode($decoded));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('MAC is invalid');
        Encryption::decrypt($tampered);
    }

    public function test_malformed_payload_is_rejected(): void
    {
        $this->expectException(\Exception::class);
        Encryption::decrypt(base64_encode('{"not":"an envelope"}'));
    }
}
