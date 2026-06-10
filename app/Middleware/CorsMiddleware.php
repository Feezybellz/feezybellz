<?php

namespace App\Middleware;

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;

class CorsMiddleware
{
    /**
     * Handle the incoming request
     * 
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        // Handle preflight OPTIONS request
        if ($request->method() === 'OPTIONS') {
            $response = new Response();
            $this->applyHeaders($response);
            $response->setStatusCode(204);
            return $response;
        }

        $response = $next($request);
        
        if ($response instanceof Response) {
            $this->applyHeaders($response);
        }

        return $response;
    }

    /**
     * Apply CORS headers to the response
     * 
     * @param Response $response
     * @return void
     */
    protected function applyHeaders(Response $response): void
    {
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN');
        $response->header('Access-Control-Max-Age', '86400');
    }
}
