<?php

namespace Tests\Unit;

use Framework\Core\Auth\JWT;
use Framework\Core\Testing\TestCase;

/**
 * Converted from the ad-hoc JWT verifier (claude_fix.md).
 *
 * An explicit secret is passed to encode/decode so the test does not
 * depend on a configured JWT secret; the app is still booted so JWT can
 * read its algorithm/issuer/audience config.
 */
class JwtTest extends TestCase
{
    private string $secret = 'unit-test-secret-key';

    public function test_encode_then_decode_returns_payload(): void
    {
        $token = JWT::encode(['sub' => 42, 'role' => 'admin'], $this->secret);
        $decoded = JWT::decode($token, $this->secret);

        $this->assertNotNull($decoded);
        $this->assertSame(42, $decoded['sub']);
        $this->assertSame('admin', $decoded['role']);
        $this->assertArrayHasKey('exp', $decoded);
        $this->assertArrayHasKey('iat', $decoded);
    }

    public function test_decode_with_wrong_secret_fails(): void
    {
        $token = JWT::encode(['sub' => 1], $this->secret);
        $this->assertNull(JWT::decode($token, 'the-wrong-secret'));
        $this->assertFalse(JWT::verify($token, 'the-wrong-secret'));
    }

    public function test_tampered_token_is_rejected(): void
    {
        $token = JWT::encode(['sub' => 1], $this->secret);
        $this->assertNull(JWT::decode($token . 'x', $this->secret));
    }

    public function test_expired_token_is_detected_and_refused(): void
    {
        // Expire well beyond the 30s clock-skew leeway.
        $token = JWT::encode(['sub' => 1], $this->secret, -3600);

        $this->assertTrue(JWT::isExpired($token));
        $this->assertNull(JWT::decode($token, $this->secret));
    }

    public function test_malformed_token_returns_null(): void
    {
        $this->assertNull(JWT::decode('not-a-jwt', $this->secret));
    }
}
