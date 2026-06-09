# Multi-Tenancy (Manual) - Developer Usage

This project uses **runtime database switching** for tenancy.

You select a tenant key, middleware resolves that tenant's DB config, then the framework replaces the active `default` connection for that request.

## What Runs The Tenant Logic

- `app/Middleware/TenantMiddleware.php`
- `app/Middleware/DatabaseManager.php`
- `core/Database/DB.php` (`DB::addConnection()` hot-swaps connections)

## Request Flow

1. Request enters middleware.
2. A tenant key is resolved (query/header/session/manual selection).
3. Tenant DB config is fetched (from central storage).
4. `DB::addConnection('default', $tenantConfig)` is called.
5. Controller/model code runs.
6. `User::all()` and other default-connection queries hit that tenant database.

## Manual Tenant Selection Pattern

Use a simple key such as `tenant1`, `tenant2`, etc.

- Set tenant for current session: `switch_tenant=tenant1`
- Clear tenant: `switch_tenant=`

Current example route:

- `/tenant-test` in `routes/web.php`

Controller example that proves tenant switching:

- `app/Controllers/TenantTestController.php`

## How To Implement DB-Fetched Tenant Config

In middleware, resolve tenant key and fetch that tenant row from your central tenants table.
Then hot-swap the connection:

```php
$tenant = DB::connection('landlord')
    ->table('tenants')
    ->where('key', $tenantKey)
    ->first();

if ($tenant) {
    DB::addConnection('default', [
        'driver'   => $tenant['db_driver'],
        'host'     => $tenant['db_host'],
        'port'     => (int) $tenant['db_port'],
        'database' => $tenant['db_name'],
        'username' => $tenant['db_user'],
        'password' => $tenant['db_pass'],
        'charset'  => 'utf8mb4',
    ]);
}
```

## Using Models After Switch

No model changes are required for normal use.

Example:

```php
$users = \App\Models\User::all();
```

This query runs on whichever tenant connection was applied to `default` in middleware.

## What Each Connection Method Does

- `DB::addConnection($name, $config)`
  - Registers or replaces a named connection config.
  - If that connection was already open, it clears the cached driver so next use reconnects with the new config.
  - For tenanting, this is the key method used to hot-swap `default` per request.

- `DB::connection($name = 'default')`
  - Returns the active driver instance for a named connection.
  - If not created yet, it builds the driver from config and connects.
  - Used to query landlord DB (`DB::connection('landlord')`) or any explicit connection.

- `Model::setConnection($name)`
  - Sets connection name for that model instance only.
  - Example: `(new User())->setConnection('landlord')`.
  - Useful when one query must run on a non-default connection.

- `QueryBuilder::on($connection)`
  - Sets the connection name for that query builder chain.
  - Example: `DB::table('tenants')->on('landlord')->where(...)->first()`.
  - Use this for one-off queries on another connection without changing global default.

## Important Rule

Use **one tenant resolver path** per request.

If both `DatabaseManager` and `TenantMiddleware` set/reset the default connection differently, one can overwrite the other.

## Quick Verification

1. Put different user records in tenant DB A and tenant DB B.
2. Hit `/tenant-test?switch_tenant=tenant1` and note results.
3. Hit `/tenant-test?switch_tenant=tenant2` and confirm results change.
4. Hit `/tenant-test?switch_tenant=` to return to base/default DB.
