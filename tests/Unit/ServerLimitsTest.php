<?php

namespace Tests\Unit;

use Framework\Core\WebSocket\WebSocketServer;
use Framework\Core\Queue\QueueServer;
use Framework\Core\Database\MySQLDriver;
use Framework\Core\Testing\TestCase;

/**
 * Converted from test_fixes_extended.php.
 *
 * Verifies the DoS-hardening knobs added during the audit: WebSocket
 * buffer/connection caps, queue client caps, and the MySQL driver's
 * transaction-depth tracking + reconnect hooks.
 */
class ServerLimitsTest extends TestCase
{
    // Reflection-only inspection — no framework bootstrap needed.
    protected function setUp(): void
    {
    }

    public function test_websocket_server_applies_limits(): void
    {
        $ws = new WebSocketServer();
        $ws->setLimits(2_000_000, 50, 5_000);

        $this->assertSame(2_000_000, $this->prop($ws, 'maxBufferSize'));
        $this->assertSame(50, $this->prop($ws, 'maxPendingFrames'));
        $this->assertSame(5_000, $this->prop($ws, 'maxConnections'));
    }

    public function test_queue_server_applies_max_clients(): void
    {
        $qs = new QueueServer();
        $qs->setMaxClients(500);

        $this->assertSame(500, $this->prop($qs, 'maxClients'));
    }

    public function test_mysql_driver_tracks_transaction_depth(): void
    {
        $db = new MySQLDriver();
        $this->assertSame(0, $this->prop($db, 'transactionDepth'));
    }

    public function test_mysql_driver_has_reconnect_hooks(): void
    {
        $ref = new \ReflectionClass(MySQLDriver::class);
        $this->assertTrue($ref->hasProperty('config'));
        $this->assertTrue($ref->hasMethod('isConnectionLost'));
        $this->assertTrue($ref->hasMethod('reconnect'));
    }

    /** Read a private/protected property via reflection. */
    private function prop(object $obj, string $name)
    {
        $p = (new \ReflectionClass($obj))->getProperty($name);
        $p->setAccessible(true);
        return $p->getValue($obj);
    }
}
