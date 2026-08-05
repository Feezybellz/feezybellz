<?php

use Framework\Core\Routing\Router;
use Framework\Core\Http\Request;
use Framework\Core\Http\Response;

Router::get('/', function(Request $request, Response $response) {
    return Response::view('index');
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

Router::get('/image-tester', [\App\Controllers\ImageTesterController::class, 'index']);
Router::post('/image-tester', [\App\Controllers\ImageTesterController::class, 'process']);

Router::get('/__captcha/refresh', [\Framework\Core\Captcha\Captcha::class, 'refreshEndpoint']);
Router::get('/captcha-tester', [\App\Controllers\CaptchaTesterController::class, 'index']);
Router::get('/captcha-tester/challenge', [\App\Controllers\CaptchaTesterController::class, 'generateChallenge']);
Router::get('/captcha-tester/render', [\App\Controllers\CaptchaTesterController::class, 'renderField']);
Router::post('/captcha-tester/verify-manual', [\App\Controllers\CaptchaTesterController::class, 'verifyManual']);
Router::post('/captcha-tester/verify-middleware', 'captcha:demo_form', [\App\Controllers\CaptchaTesterController::class, 'verifyMiddleware']);

Router::get('/tester', [\App\Controllers\TesterController::class, 'index']);
Router::any('/tester/handle', [\App\Controllers\TesterController::class, 'handle']);

Router::get('/tenant-test', [\App\Controllers\TenantTestController::class, 'index']);

// --- WILDCARD CATCH-ALL TESTING ---

// This will match /assets/css/style.css, /assets/js/app.js, etc.
Router::get('/config/*', function(\Framework\Core\Http\Request $request) {
    return "Sorry, you're not meant to be here!!!";
});

// --- SUBDOMAIN TESTING ---

// 1. Static Subdomain Test (api.framework.net.ng)
Router::group(['subdomain' => 'api'], function() {
    Router::get('/', function($request, $response) {
        // return ['status' => 'success', 'message' => 'You have successfully hit the API subdomain!'];
        Response::json([
            'status' => 'success',
            'message' => 'You have successfully hit the API subdomain!'
        ])->send();
        
    });
    
    Router::get('/ping', function() {
        return ['pong' => true];
    });
});

// 2. Dynamic Wildcard Subdomain Test (e.g. acme.framework.net.ng)
Router::group(['subdomain' => '{tenant}'], function() {
    Router::get('/', function(Framework\Core\Http\Request $request) {
        $tenantId = $request->route('tenant');
        return "<h1>Tenant Portal</h1><p>Welcome to the dedicated portal for: <b>{$tenantId}</b></p>";
    });
    
    Router::get('/database', function(Framework\Core\Http\Request $request) {
        $tenantId = $request->route('tenant');
        return [
            'tenant' => $tenantId,
            'simulated_db_connection' => "framework_client_{$tenantId}"
        ];
    });
});
