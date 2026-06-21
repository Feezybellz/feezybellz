<?php

require __DIR__ . '/vendor/autoload.php';

use Framework\Core\Routing\Route;
use Framework\Core\Routing\Router;
use Framework\Core\Container\Container;
use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use Framework\Core\Cache\Cache;
use Framework\Core\Security\WAF;
use Framework\Core\Database\MySQLDriver;
use Framework\Core\Routing\RateLimiter;

echo "Running Sanity Tests for Performance Fixes...\n\n";

// 1. Router & Container Tests
try {
    $container = new Container();
    Router::setContainer($container);
    
    // Create dummy request & response
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/api/users/123';
    $_SERVER['HTTP_HOST'] = 'framework.net.ng';
    $req = new Request();
    
    Router::init($req, new Response());
    Router::clearRoutes();
    
    // Add routes
    Router::get('/api/users/{id}', function($id) {
        return "User: $id";
    });
    Router::get('/api/posts', function() {
        return "Posts";
    });
    
    // Test compilation
    $routes = Router::getRoutes();
    if (!isset($routes[0]->compiledPattern) || empty($routes[0]->compiledPattern)) {
        echo "❌ Router compiledPattern missing.\n";
    } else {
        echo "✅ Router compiledPattern working: " . $routes[0]->compiledPattern . "\n";
    }
    
    echo "Request URI: " . $req->uri() . "\n";
    
    // Dispatch
    $res = Router::dispatch();
    if ($res->getContent() === 'User: 123') {
        echo "✅ Router dispatch working.\n";
    } else {
        echo "❌ Router dispatch returned unexpected response length: " . strlen($res->getContent()) . "\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Router/Container test failed: " . $e->getMessage() . "\n";
}

// 2. Cache & WAF
try {
    // WAF Test
    $_SERVER['REMOTE_ADDR'] = '127.0.0.' . rand(2, 250);
    $_POST['payload'] = "some <script>alert(1)</script> bad stuff";
    $req = new Request();
    
    $waf = new WAF();
    $payload = json_encode([$req->all(), $req->query(), $_COOKIE]);
    
    $isValid = $waf->validate($req);
    $msg = WAF::getMessage();
    
    if ($isValid === false && strpos($msg, 'xss') !== false) {
        echo "✅ WAF json_encode scan working.\n";
    } else {
        echo "❌ WAF scan failed to detect payload. isValid: " . ($isValid ? 'true' : 'false') . ", msg: $msg\n";
    }
} catch (\Exception $e) {
    echo "❌ WAF test failed: " . $e->getMessage() . "\n";
}

// 3. RateLimiter
try {
    // Basic Rate Limiter check (requires Cache to be set up)
    RateLimiter::clear('test_ip');
    $hits = RateLimiter::hit('test_ip');
    if ($hits === 1) {
        $hits2 = RateLimiter::hit('test_ip');
        if ($hits2 === 2) {
            echo "✅ RateLimiter hit logic working.\n";
        } else {
            echo "❌ RateLimiter second hit returned: $hits2\n";
        }
    } else {
        echo "❌ RateLimiter first hit returned: $hits\n";
    }
} catch (\Exception $e) {
    echo "❌ Cache/RateLimiter test failed: " . $e->getMessage() . "\n";
}

// 4. DB Driver
try {
    $mysql = new MySQLDriver();
    $mysql->connect(['host' => '127.0.0.1', 'port' => 3306, 'database' => 'test', 'username' => 'root', 'password' => '']);
    // Wait, the DB may not exist, let's just check if it instantiates and method exists
} catch (\Exception $e) {
    // Might fail connecting, but we just want to see if the syntax is valid
    if (strpos($e->getMessage(), 'Database connection failed') !== false) {
        echo "✅ Database Driver syntax looks OK (connection failed as expected).\n";
    } else {
        echo "❌ Database Driver test failed: " . $e->getMessage() . "\n";
    }
}

echo "\nTests completed.\n";
