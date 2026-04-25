<?php

namespace App\Middleware;

use Framework\Core\Http\Middleware;
use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use Framework\Core\Database\DB;

class TenantMiddleware implements Middleware
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, callable $next): Response
    {
        $tenantKey = $this->resolveTenant($request);

        if ($tenantKey) {
            $this->applyTenantConnection($tenantKey);
        } else {
            $this->revertToDefaultConnection();
        }

        return $next($request);
    }

    /**
     * Resolve tenant from session or query parameter.
     */
    protected function resolveTenant(Request $request): ?string
    {
        // 1. Check for tenant change in query string
        $requestedTenant = $request->query('switch_tenant');
        
        if ($requestedTenant !== null) {
            session()->set('active_tenant', $requestedTenant);
            return $requestedTenant ?: null;
        }

        // 2. Get active tenant from session
        return session()->get('active_tenant');
    }

    /**
     * Apply the tenant connection to the DB manager.
     */
    protected function applyTenantConnection(string $tenantKey): void
    {
        $tenants = config('tenant.tenants', []);
        
        if (isset($tenants[$tenantKey])) {
            DB::addConnection('default', $tenants[$tenantKey]);
        } else {
            $this->revertToDefaultConnection();
        }
    }

    /**
     * Revert to the original default connection from config/db.php
     */
    protected function revertToDefaultConnection(): void
    {
        $defaultConfig = config('db.connections.default');
        if ($defaultConfig) {
            DB::addConnection('default', $defaultConfig);
        }
    }
}
