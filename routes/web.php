<?php

use Framework\Core\Routing\Router;
use Framework\Core\Http\Request;
use Framework\Core\Http\Response;

Router::get('/', function(Request $request, Response $response) {
    return "<h1>Framework Initialized</h1><p>Welcome to your new project.</p>";
});
