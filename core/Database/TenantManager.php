<?php

namespace Framework\Core\Database;

use Framework\Core\Http\Request;

class TenantManager
{
    /** @var \Closure|null */
    protected static $resolver = null;

    /**
     * Register a custom callback to resolve the tenant dynamically.
     */
    public static function resolveUsing(\Closure $callback): void
    {
        self::$resolver = $callback;
    }

    /**
     * Resolve and boot the correct tenant database
     */
    public static function boot(Request $request): void
    {
        // 1. Identify Tenant from subdomain
        $host = $request->host();
        $subdomain = explode('.', $host)[0];

        // 2. Allow Developer to Override Resolution Logic
        if (self::$resolver !== null) {
            $resolver = self::$resolver;
            $connectionDetails = $resolver($subdomain, $request);
            
            if (is_array($connectionDetails)) {
                DB::addConnection('default', $connectionDetails);
            }
            return;
        }

        $config = config('db');

        // 3. Priority 1: Check Static Config (Fastest)
        if (isset($config['static_tenants'][$subdomain])) {
            DB::addConnection('default', $config['static_tenants'][$subdomain]);
            return;
        }

        // 3. Priority 2: Check Landlord Database
        try {
            // Ensure landlord connection is initialized
            if (isset($config['connections']['landlord'])) {
                // We use the 'landlord' connection to find the tenant
                $tenantData = DB::connection('landlord')->query(
                    "SELECT db_host, db_name, db_user, db_pass FROM tenants WHERE subdomain = ? AND status = 'active' LIMIT 1",
                    [$subdomain]
                );

                if (!empty($tenantData)) {
                    $tenant = $tenantData[0];
                    DB::addConnection('default', [
                        'driver'   => 'mysql',
                        'host'     => $tenant['db_host'],
                        'database' => $tenant['db_name'],
                        'username' => $tenant['db_user'],
                        'password' => $tenant['db_pass'],
                    ]);
                    return;
                }
            }
        } catch (\Exception $e) {
            if (class_exists('\Framework\Core\Logging\Log')) {
                \Framework\Core\Logging\Log::error("Tenant resolution failed: " . $e->getMessage());
            }
        }

        // 4. Final Fallback: If no tenant found, let index.php use the default connection
    }
}