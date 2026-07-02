<?php

use Framework\Core\Routing\Router;

/*
|--------------------------------------------------------------------------
| Application Middleware Aliases
|--------------------------------------------------------------------------
|
| Register YOUR app's middleware aliases here. The framework's built-in
| aliases (csrf, cors, security, throttle, waf) are already registered in
| core/Http/Kernel.php and don't need to be re-listed unless you want to
| override one with your own subclass.
|
| This file is loaded after the Kernel's $routeMiddleware array, so any
| alias you register here takes precedence on key collision.
|
| Example: register an app-specific auth middleware
|
|     Router::aliasMiddleware([
|         'auth'      => \App\Middleware\AuthMiddleware::class,
|         'admin'     => \App\Middleware\RequireAdmin::class,
|     ]);
|
| Example: override a framework default with your own subclass
|
|     Router::registerAlias('csrf', \App\Middleware\MyCustomCsrf::class);
*/

Router::aliasMiddleware([
    // Add app aliases here.
]);
