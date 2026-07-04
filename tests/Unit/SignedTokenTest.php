<?php

namespace Tests\Unit;

use Framework\Core\Auth\SignedToken;
use Framework\Core\Testing\TestCase;
use Tests\WithAppKey;

/**
 * Converted from the ad-hoc SignedToken verifier (claude_fix.md).
 *
 * SignedToken signs with APP_KEY, so we boot with a test key.
 */
class SignedTokenTest extends TestCase
{
    use WithAppKey;

    protected function setUp(): void
    {
        $this->bootWithAppKey();
    }

    public function test_issue_then_verify_returns_payload(): void
    {
        $token = SignedToken::issue(['user' => 7], 60);
        $payload = SignedToken::verify($token);

        $this->assertNotNull($payload);
        $this->assertSame(7, $payload['user']);
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = SignedToken::issue(['user' => 7], -1);
        $this->assertNull(SignedToken::verify($token));
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $token = SignedToken::issue(['user' => 7], 60);
        [$body, $sig] = explode('.', $token, 2);
        $this->assertNull(SignedToken::verify($body . '.' . strrev($sig)));
    }

    public function test_garbage_token_returns_null(): void
    {
        $this->assertNull(SignedToken::verify('garbage'));
    }
}
