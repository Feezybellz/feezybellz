<?php

namespace Framework\Core\Http\Middleware;

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use Framework\Core\Security\WAF;

class WafMiddleware
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next, array $params = [])
    {
        foreach ($params as $param) {
            // Check for explicit "driver=db" or just "db"
            if ($param === 'db' || strpos($param, 'driver=db') !== false) {
                WAF::setBlockDriver('db');
            }
            if ($param === 'file' || strpos($param, 'driver=file') !== false) {
                WAF::setBlockDriver('file');
            }

            // Check for "block=7200" or "block:7200"
            if (strpos($param, 'block=') === 0) {
                $duration = (int) substr($param, 6);
                WAF::setBlockDuration($duration);
            } elseif (strpos($param, 'block:') === 0) {
                $duration = (int) substr($param, 6);
                WAF::setBlockDuration($duration);
            } elseif (is_numeric($param)) {
                // Keep backward compatibility for just passing "7200"
                WAF::setBlockDuration((int) $param);
            }
        }
        
        // Run the Web Application Firewall scan
        if (!WAF::scan($request)) {
            $response = new Response();
            $response->setStatusCode(403);
            
            $reason = WAF::getMessage() ?: 'Malicious Payload Detected';
            
            // Format response gracefully based on request type
            if ($request->wantsJson() || strpos($request->uri(), '/api/') === 0) {
                $response->json([
                    'error' => true,
                    'message' => 'Forbidden: ' . $reason
                ]);
            } else {
                $html = "<!DOCTYPE html><html><head><title>403 Forbidden</title>";
                $html .= "<style>body { font-family: system-ui; background: #111; color: #fff; padding: 3rem; text-align: center; } h1 { color: #ef4444; }</style></head>";
                $html .= "<body><h1>403 Forbidden</h1><p>Your request was blocked by the Web Application Firewall.</p>";
                $html .= "<p style='color:#9ca3af;'>Reason: " . htmlspecialchars($reason) . "</p></body></html>";
                
                $response->setContent($html);
            }
            
            return $response;
        }

        return $next($request);
    }
}
