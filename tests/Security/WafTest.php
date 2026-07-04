<?php

namespace Tests\Security;

use Framework\Core\Security\WAF;
use Framework\Core\Http\Request;
use Framework\Core\Testing\TestCase;

/**
 * Converted from test_fixes.php (section 2).
 *
 * The WAF must flag an XSS payload arriving via POST body.
 */
class WafTest extends TestCase
{
    public function test_xss_payload_is_flagged(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/submit';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.' . rand(2, 250);
        $_POST['payload'] = 'some <script>alert(1)</script> bad stuff';

        $waf = new WAF();
        $valid = $waf->validate(new Request());

        $this->assertFalse($valid);
        $this->assertStringContainsString('xss', strtolower(WAF::getMessage()));
    }

    public function test_clean_request_passes(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/submit';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.' . rand(2, 250);
        $_POST = ['payload' => 'a perfectly ordinary comment'];

        $waf = new WAF();
        $this->assertTrue($waf->validate(new Request()));
    }

    protected function tearDown(): void
    {
        $_POST = [];
        unset($_SERVER['CONTENT_TYPE']);
    }
}
