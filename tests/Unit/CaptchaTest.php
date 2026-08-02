<?php

namespace Tests\Unit;

use Framework\Core\Testing\TestCase;
use Framework\Core\Captcha\Captcha;
use Framework\Core\Captcha\Exceptions\CaptchaException;
use Framework\Core\Cache\Cache;

class CaptchaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // Ensure APP_KEY exists for SignedToken during unit test runs
        if (empty($_ENV['APP_KEY']) && (!function_exists('config') || !config('app.key'))) {
            $_ENV['APP_KEY'] = 'base64:' . base64_encode(random_bytes(32));
        }
    }

    public function test_challenge_generation(): void
    {
        $ch = Captcha::challenge('contact_form', 2, 300);

        $this->assertNotEmpty($ch['token']);
        $this->assertNotEmpty($ch['nonce']);
        $this->assertEquals(2, $ch['difficulty']);
        $this->assertEquals('contact_form', $ch['name']);
        $this->assertEquals(300, $ch['ttl']);
    }

    public function test_captcha_field_rendering(): void
    {
        $html = Captcha::captcha_field('login_box', ['difficulty' => 2]);

        $this->assertStringContainsString('name="captcha_token"', $html);
        $this->assertStringContainsString('name="captcha_solution"', $html);
        $this->assertStringContainsString('name="captcha_entropy"', $html);
        $this->assertStringContainsString('value="login_box"', $html);
        $this->assertStringContainsString('crypto.subtle.digest', $html);
    }

    public function test_successful_verification_and_replay_protection(): void
    {
        $ch = Captcha::challenge('secure_form', 2, 600);
        $solution = Captcha::solve($ch['nonce'], 2);

        $input = [
            'captcha_token' => $ch['token'],
            'captcha_solution' => (string) $solution,
            'captcha_entropy' => '1',
            'captcha_name' => 'secure_form',
        ];

        // 1. First verification should succeed
        $this->assertTrue(Captcha::verify($input, 'secure_form'));

        // 2. Second attempt with the exact same valid payload should FAIL (Replay attack blocked via Cache)
        $this->assertFalse(Captcha::verify($input, 'secure_form'));
    }

    public function test_invalid_proof_of_work_is_rejected(): void
    {
        $ch = Captcha::challenge('test_form', 3, 600);

        $input = [
            'captcha_token' => $ch['token'],
            'captcha_solution' => '999999999', // Incorrect counter
            'captcha_entropy' => '1',
            'captcha_name' => 'test_form',
        ];

        $this->assertFalse(Captcha::verify($input));

        $this->expectException(CaptchaException::class);
        $this->expectExceptionMessage('The submitted Proof-of-Work computation solution is incorrect');
        Captcha::verifyOrFail($input);
    }

    public function test_form_name_scope_mismatch_is_rejected(): void
    {
        $ch = Captcha::challenge('login', 2, 600);
        $solution = Captcha::solve($ch['nonce'], 2);

        $input = [
            'captcha_token' => $ch['token'],
            'captcha_solution' => (string) $solution,
            'captcha_entropy' => '1',
            'captcha_name' => 'login',
        ];

        // Try to verify against expected name 'password_reset'
        $this->assertFalse(Captcha::verify($input, 'password_reset'));
    }

    public function test_helper_functions(): void
    {
        $engine = captcha();
        $this->assertInstanceOf(Captcha::class, $engine);

        $fieldHtml = captcha_field('signup_form');
        $this->assertStringContainsString('value="signup_form"', $fieldHtml);
    }

    public function test_dynamic_interactive_modes_rendering(): void
    {
        $slider = captcha_field('auth', ['mode' => 'slider']);
        $this->assertStringContainsString('data-mode="slider"', $slider);
        $this->assertStringContainsString('Slide to verify', $slider);

        $turnstile = captcha_field('auth', ['mode' => 'turnstile']);
        $this->assertStringContainsString('data-mode="turnstile"', $turnstile);
        $this->assertStringContainsString('Verify you are human', $turnstile);

        $puzzle = captcha_field('auth', ['mode' => 'puzzle']);
        $this->assertStringContainsString('data-mode="puzzle"', $puzzle);
        $this->assertStringContainsString('Drag piece to complete puzzle', $puzzle);
        $this->assertStringContainsString('data:image', $puzzle);
    }

    public function test_puzzle_mode_verification_requires_accurate_alignment(): void
    {
        $targetX = 140;
        $ch = Captcha::challenge('puzzle_form', 2, 600, $targetX);
        $solution = Captcha::solve($ch['nonce'], 2);

        $inputInvalid = [
            'captcha_token' => $ch['token'],
            'captcha_solution' => (string) $solution,
            'captcha_entropy' => '1',
            'captcha_name' => 'puzzle_form',
            'captcha_puzzle_x' => '90', // 50px off from target 140!
        ];

        // Should fail due to inaccurate puzzle piece alignment
        $this->assertFalse(Captcha::verify($inputInvalid, 'puzzle_form'));

        $inputValid = [
            'captcha_token' => $ch['token'],
            'captcha_solution' => (string) $solution,
            'captcha_entropy' => '1',
            'captcha_name' => 'puzzle_form',
            'captcha_puzzle_x' => '143', // 3px difference (within 7px human tolerance)
        ];

        // Should pass clean verification and burn nonce
        $this->assertTrue(Captcha::verify($inputValid, 'puzzle_form'));
    }
}
