<?php

use Framework\Core\Routing\Router;

/*
|--------------------------------------------------------------------------
| Application Middleware Aliases
|--------------------------------------------------------------------------
|
| Here you may register all of the middleware aliases for your application.
| These aliases can be used to quickly attach middleware to routes or 
| route groups without needing to specify the full namespace.
|
*/

Router::aliasMiddleware([
    'throttle' => \Framework\Core\Http\Middleware\ThrottleRequests::class,
    'waf'      => \Framework\Core\Http\Middleware\WafMiddleware::class,
    'csrf'     => \App\Middleware\CsrfMiddleware::class,
    'cors'     => \App\Middleware\CorsMiddleware::class,
]);

// You can also register them individually!
// Router::registerAlias('auth', \App\Middleware\AuthMiddleware::class);
