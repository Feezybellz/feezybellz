<?php

namespace Tests\Unit;

use Framework\Core\Security\Hash;
use Framework\Core\Testing\TestCase;

/**
 * Converted from the ad-hoc Hash verifier (claude_fix.md).
 *
 * Uses the default (app-booting) setUp so Hash can read its configured
 * algorithm/options.
 */
class HashTest extends TestCase
{
    public function test_make_produces_a_verifiable_hash(): void
    {
        $hash = Hash::make('correct horse battery staple');

        $this->assertNotEquals('correct horse battery staple', $hash);
        $this->assertTrue(Hash::check('correct horse battery staple', $hash));
    }

    public function test_check_rejects_wrong_value(): void
    {
        $hash = Hash::make('s3cret');
        $this->assertFalse(Hash::check('wrong', $hash));
    }

    public function test_check_on_empty_hash_is_false(): void
    {
        $this->assertFalse(Hash::check('anything', ''));
    }

    public function test_fresh_hash_does_not_need_rehash(): void
    {
        $this->assertFalse(Hash::needsRehash(Hash::make('pw')));
    }
}
