<?php

/**
 * Front Controller
 */

// Start session
session_start();

// Load the autoloader
require __DIR__ . '/../bootstrap/autoload.php';

use Framework\Core\Application; // Use the Application class
use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use Framework\Core\Routing\Router;
use Framework\Core\Database\DB;
use Framework\Core\Database\TenantManager;
use Framework\Core\Events\Dispatcher;

// 1. Initialize the DI Container / Application
$app = new Application(dirname(__DIR__));

// 3. Create request object
$request = new Request();

// 4. Resolve Tenant (Dynamic Database Switching)
TenantManager::boot($request);


Dispatcher::register(require __DIR__ . '/../config/events.php');

// 5. Initialize router with request, response, and the CONTAINER
$response = new Response();
Router::setContainer($app); // This allows the router to auto-wire your controllers
Router::init($request, $response);

// 6. Load all route files
Router::loadRoutesFrom(__DIR__ . '/../routes');

// 7. Dispatch the request
// DISPATCH THE ROUTE
// This is where the magic happens:
$result = Router::dispatch($request, $response);

// Security headers — applied to every response
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');

// THE HANDSHAKE:
if ($result instanceof Response) {
    // This handles the status code (403), headers, and JSON body
    $result->send();
}
elseif (is_string($result)) {
    echo $result;
}