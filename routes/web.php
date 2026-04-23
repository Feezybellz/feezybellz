<?php

use Framework\Core\Routing\Router;
use Framework\Core\Http\Request;
use Framework\Core\Http\Response;

Router::get('/', function(Request $request, Response $response) {
    return "<h1>Framework Initialized</h1><p>Welcome to your new project.</p>";
});

Router::get('/safe-test', function(Request $request, Response $response) {
    return "<h1>Safe Route</h1><p>This route does not use the database. It should load even if your DB is broken.</p>";
});

Router::get('/db-test', function(Request $request, Response $response) {
    try {
        $users = \App\Models\User::all();
        return Response::json([
            'success' => true,
            'count' => count($users),
            'message' => 'Database connection successful!'
        ]);
    } catch (\Exception $e) {
        return Response::setStatusCode(500)->json([
            'success' => false,
            'error' => $e->getMessage(),
            'message' => 'Database connection failed as expected when misconfigured.'
        ]);
    }
});

Router::get('/smtp-tester', [\App\Controllers\SmtpTesterController::class, 'index']);
Router::post('/smtp-tester', [\App\Controllers\SmtpTesterController::class, 'index']);

