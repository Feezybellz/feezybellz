<?php

/**
 * Front Controller
 */

// Load the autoloader
require __DIR__ . '/../bootstrap/autoload.php';

use Framework\Core\Application;
use Framework\Core\Http\Kernel;
use Framework\Core\Http\Request;

// 1. Initialize the Application
$app = new Application(dirname(__DIR__));
$app->instance(Application::class, $app);
$app->instance(\Framework\Core\Container\Container::class, $app);

// Tell the Application where the public folder is (dynamically sets it to the folder this index.php is in)
Application::usePublicPath(__DIR__);

// 2. Resolve the Kernel
$kernel = $app->make(Kernel::class);

// 3. Handle the incoming request
$request = new Request();
$response = $kernel->handle($request);

// 4. Send the response and terminate
$kernel->terminate($request, $response);
$response->send();
