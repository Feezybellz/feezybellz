<?php

require __DIR__ . '/vendor/autoload.php';

use Framework\Core\WebSocket\WebSocketServer;
use Framework\Core\Queue\QueueServer;
use Framework\Core\Database\MySQLDriver;

echo "Running Extended Sanity Tests for Fixes...\n\n";

// 1. WebSocketServer Limits Check
try {
    $ws = new WebSocketServer();
    $ws->setLimits(2000000, 50, 5000);
    
    $ref = new ReflectionClass($ws);
    $p1 = $ref->getProperty('maxBufferSize');
    $p1->setAccessible(true);
    $p2 = $ref->getProperty('maxPendingFrames');
    $p2->setAccessible(true);
    $p3 = $ref->getProperty('maxConnections');
    $p3->setAccessible(true);
    
    if ($p1->getValue($ws) === 2000000 && $p2->getValue($ws) === 50 && $p3->getValue($ws) === 5000) {
        echo "✅ WebSocketServer connection limits and buffer caps successfully configured.\n";
    } else {
        echo "❌ WebSocketServer limits failed to apply.\n";
    }
} catch (\Exception $e) {
    echo "❌ WebSocketServer test failed: " . $e->getMessage() . "\n";
}

// 2. QueueServer Limits Check
try {
    $qs = new QueueServer();
    $qs->setMaxClients(500);
    
    $ref = new ReflectionClass($qs);
    $p1 = $ref->getProperty('maxClients');
    $p1->setAccessible(true);
    
    if ($p1->getValue($qs) === 500) {
        echo "✅ QueueServer maximum client connections limit successfully applied.\n";
    } else {
        echo "❌ QueueServer max clients limit failed to apply.\n";
    }
} catch (\Exception $e) {
    echo "❌ QueueServer test failed: " . $e->getMessage() . "\n";
}

// 3. MySQLDriver Transaction Depth Check
try {
    $db = new MySQLDriver();
    $ref = new ReflectionClass($db);
    
    if ($ref->hasProperty('transactionDepth')) {
        $prop = $ref->getProperty('transactionDepth');
        $prop->setAccessible(true);
        if ($prop->getValue($db) === 0) {
            echo "✅ MySQLDriver transaction depth tracker correctly initialized.\n";
        } else {
            echo "❌ MySQLDriver transaction depth not initialized to 0.\n";
        }
    } else {
        echo "❌ MySQLDriver missing transactionDepth property.\n";
    }
    
    // Check if the auto-reconnect property/method exists
    if ($ref->hasProperty('config') && $ref->hasMethod('isConnectionLost') && $ref->hasMethod('reconnect')) {
        echo "✅ MySQLDriver connection health-check and auto-reconnect hooks verified.\n";
    } else {
        echo "❌ MySQLDriver missing auto-reconnect properties/methods.\n";
    }
} catch (\Exception $e) {
    echo "❌ MySQLDriver test failed: " . $e->getMessage() . "\n";
}

echo "\nExtended Tests completed.\n";
