<?php

namespace Tests\Unit;

use Framework\Core\Support\ClosureSerializer;
use Framework\Core\Testing\TestCase;
use Tests\WithAppKey;

/**
 * Converted from the ad-hoc ClosureSerializer verifier (claude_fix.md).
 *
 * The serializer signs with APP_KEY, so we boot with a test key.
 * Deserialization is gated behind an explicit config flag (disabled by
 * default) — that security posture is asserted here.
 */
class ClosureSerializerTest extends TestCase
{
    use WithAppKey;

    protected function setUp(): void
    {
        $this->bootWithAppKey();
    }

    public function test_serialize_produces_a_signed_envelope(): void
    {
        $payload = ClosureSerializer::serialize(function () {
            return 'hello';
        });

        $this->assertNotEmpty($payload);
        // Base64 outer envelope decodes to a JSON body+signature pair.
        $envelope = json_decode(base64_decode($payload), true);
        $this->assertArrayHasKey('body', $envelope);
        $this->assertArrayHasKey('sig', $envelope);
    }

    public function test_deserialize_refuses_when_feature_disabled(): void
    {
        // Deserialization is off by default — a hard security boundary,
        // since it reconstructs and can execute code.
        if (ClosureSerializer::enabled()) {
            $this->markTestSkipped('closure_serializer.enabled is on in this environment.');
        }

        $payload = ClosureSerializer::serialize(fn () => 1);

        $this->expectException(\RuntimeException::class);
        ClosureSerializer::deserialize($payload);
    }
}
