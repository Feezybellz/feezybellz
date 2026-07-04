<?php

namespace Tests\Unit;

use Framework\Core\WebSocket\WebSocketServer;
use Framework\Core\WebSocket\WS;
use Framework\Core\Testing\TestCase;

/**
 * Internal trigger port authentication (remaining.md §8.3.2): the WS
 * daemon must reject unsigned, tampered, or replayed broadcast payloads —
 * loopback binding alone does not authenticate local peers.
 */
class WsInternalAuthTest extends TestCase
{
    private WebSocketServer $server;

    /** @var array<int, array> events that made it through to handlers */
    private array $received = [];

    private string $secret = 'unit-test-internal-secret';

    protected function setUp(): void
    {
        $this->server = new WebSocketServer('127.0.0.1', 0, 0);
        $this->server->setSilent(true);
        $this->server->setInternalSecret($this->secret);

        $this->received = [];
        $this->server->on('broadcast', function ($data) {
            $this->received[] = $data;
        });
    }

    /** Feed raw bytes into the private internal-trigger handler. */
    private function trigger(string $wire): void
    {
        $m = new \ReflectionMethod($this->server, 'handleInternalTrigger');
        $m->setAccessible(true);
        $m->invoke($this->server, $wire);
    }

    public function test_valid_envelope_is_accepted(): void
    {
        $payload = ['event' => 'broadcast', 'data' => ['event' => 'news', 'payload' => ['x' => 1]]];

        $this->trigger(WS::buildEnvelope($payload, $this->secret));

        $this->assertCount(1, $this->received);
        $this->assertSame('news', $this->received[0]['data']['event']);
    }

    public function test_unsigned_legacy_payload_is_rejected_when_secured(): void
    {
        $this->trigger(json_encode(['event' => 'broadcast', 'data' => ['event' => 'evil']]));

        $this->assertEmpty($this->received);
    }

    public function test_wrong_secret_is_rejected(): void
    {
        $payload = ['event' => 'broadcast', 'data' => []];

        $this->trigger(WS::buildEnvelope($payload, 'some-other-secret'));

        $this->assertEmpty($this->received);
    }

    public function test_tampered_payload_is_rejected(): void
    {
        $envelope = json_decode(
            WS::buildEnvelope(['event' => 'broadcast', 'data' => ['event' => 'ok']], $this->secret),
            true
        );
        // Swap the payload but keep the original signature.
        $envelope['payload'] = json_encode(['event' => 'broadcast', 'data' => ['event' => 'evil']]);

        $this->trigger(json_encode($envelope));

        $this->assertEmpty($this->received);
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $payload = ['event' => 'broadcast', 'data' => []];

        // Signed correctly, but outside the replay window (both directions).
        $this->trigger(WS::buildEnvelope($payload, $this->secret, time() - WS::REPLAY_WINDOW - 5));
        $this->trigger(WS::buildEnvelope($payload, $this->secret, time() + WS::REPLAY_WINDOW + 5));

        $this->assertEmpty($this->received);
    }

    public function test_timestamp_swap_replay_is_rejected(): void
    {
        // The signature covers "ts.payload", so refreshing the timestamp
        // on a captured envelope must invalidate it.
        $envelope = json_decode(
            WS::buildEnvelope(['event' => 'broadcast', 'data' => []], $this->secret, time() - 3600),
            true
        );
        $envelope['ts'] = time(); // attacker "refreshes" the stale capture

        $this->trigger(json_encode($envelope));

        $this->assertEmpty($this->received);
    }

    public function test_unsigned_allowed_only_when_explicitly_opted_out(): void
    {
        $server = new WebSocketServer('127.0.0.1', 0, 0);
        $server->setSilent(true);
        $server->setInternalSecret('');
        $server->setRequireInternalSignature(false); // conscious dev opt-out

        $received = [];
        $server->on('broadcast', function ($data) use (&$received) {
            $received[] = $data;
        });

        $m = new \ReflectionMethod($server, 'handleInternalTrigger');
        $m->setAccessible(true);
        $m->invoke($server, json_encode(['event' => 'broadcast', 'data' => ['event' => 'dev']]));

        $this->assertCount(1, $received);
    }

    public function test_server_refuses_to_boot_secured_but_keyless(): void
    {
        $server = new WebSocketServer('127.0.0.1', 0, 0);
        $server->setSilent(true);
        // require_internal_signature defaults to true; no secret set.

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no secret is configured');
        $server->start();
    }

    public function test_secret_resolution_unwraps_base64_app_key(): void
    {
        // WS::internalSecret falls back to APP_KEY and must unwrap the
        // base64: prefix so HMAC uses raw key bytes.
        $raw = str_repeat('k', 32);
        $key = 'base64:' . base64_encode($raw);
        $_ENV['APP_KEY'] = $key;
        $_SERVER['APP_KEY'] = $key;
        putenv('APP_KEY=' . $key);
        config('__reload__', 'app');

        $this->assertSame($raw, WS::internalSecret());

        $_ENV['APP_KEY'] = '';
        $_SERVER['APP_KEY'] = '';
        putenv('APP_KEY=');
        config('__reload__', 'app');
    }
}
