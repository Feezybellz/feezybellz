<?php

namespace App\Middleware;

use Framework\Core\Http\Middleware;
use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use Framework\Core\Database\DB;

class DatabaseManager implements Middleware
{
    /**
     * Handle the incoming request.
     * 
     * This middleware manages dynamic database connections (e.g. for multi-tenancy).
     * It runs on every single visit (request) before the controller is executed.
     */
    public function handle(Request $request, callable $next): Response
    {
        /*
        |--------------------------------------------------------------------------
        | METHOD 1: Dynamic Resolution from a Landlord Database
        |--------------------------------------------------------------------------
        | Useful for large-scale multi-tenancy where each user has a unique DB.
        |
        | 1. Identify the visitor (e.g., via Host header)
        |    $host = $request->header('Host'); 
        |
        | 2. Query your 'landlord' database connection for tenant-specific details
        |    $tenant = DB::table('tenants')->on('landlord')->where('domain', $host)->first();
        |
        | 3. If found, hot-swap the 'default' connection so the app uses the tenant's DB
        |    if ($tenant) {
        |        DB::addConnection('default', [
        |            'driver'   => $tenant['db_driver'], // Supports 'mysql' or 'mongodb'
        |            'host'     => $tenant['db_host'],
        |            'database' => $tenant['db_name'],
        |            'username' => $tenant['db_user'],
        |            'password' => $tenant['db_pass'],
        |        ]);
        |    }
        */

        /*
        |--------------------------------------------------------------------------
        | METHOD 2: Resolution from Configuration / Static Mapping
        |--------------------------------------------------------------------------
        | Useful for regional databases or fixed "static" tenants.
        |
        | 1. Identify the visitor (e.g. via a custom tenant header)
        |    $tenantKey = $request->header('X-Tenant-Key');
        |
        | 2. Look up the configuration from your config/db.php 'static_tenants' array
        |    $config = config("db.static_tenants.$tenantKey");
        |
        | 3. Hot-swap the 'default' connection
        |    if ($config) {
        |        DB::addConnection('default', $config);
        |    }
        */

        return $next($request);
    }
}
