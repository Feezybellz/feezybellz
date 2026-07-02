<?php

namespace Framework\Core\Database;

/**
 * Connection registry and static facade.
 *
 * Multi-tenancy contract:
 *   - addConnection($name, $config) registers/replaces a config slot.
 *   - If a driver was previously open under that slot, it is explicitly
 *     disconnected before the new config takes effect. This matters for
 *     long-running processes (queue workers, websocket server) where the
 *     same PHP process handles many tenants and PDO file descriptors
 *     would otherwise pile up across hot-swaps.
 *   - connection($name) is lazy — the driver is only built on first use.
 *   - Apps switching tenants in middleware just call addConnection('default', ...).
 *     Nothing else changes; Model/QueryBuilder pick up the new connection on
 *     their next call without holding stale references.
 */
class DB
{
    /** @var array<string, array> Connection configurations, keyed by name. */
    protected static $connections = [];

    /** @var array<string, DatabaseDriverInterface> Active driver instances. */
    protected static $drivers = [];

    /** @var string Name of the connection used when none is specified. */
    protected static $defaultConnection = 'default';

    /** @var array<callable> Registered query listeners. */
    protected static $listeners = [];

    /**
     * Register or replace a connection config. If a driver is currently active
     * under this name, disconnect it cleanly before discarding the reference.
     */
    public static function addConnection(string $name, array $config): void
    {
        self::purge($name);
        self::$connections[$name] = $config;
    }

    /**
     * Force-disconnect the named connection's driver (if open) and drop the
     * cached instance. Next call to connection($name) rebuilds from config.
     *
     * Useful for tenant hot-swaps and for tests that need a fresh PDO.
     */
    public static function purge(string $name): void
    {
        if (isset(self::$drivers[$name])) {
            $driver = self::$drivers[$name];
            if (method_exists($driver, 'disconnect')) {
                try {
                    $driver->disconnect();
                } catch (\Throwable $e) {
                    // Best-effort cleanup; never let teardown crash an active request.
                }
            }
            unset(self::$drivers[$name]);
        }
    }

    /**
     * Purge every cached driver. Use sparingly — primarily for full reset in
     * tests or before a forked worker rehydrates connections in its child.
     */
    public static function purgeAll(): void
    {
        foreach (array_keys(self::$drivers) as $name) {
            self::purge($name);
        }
    }

    public static function setDefaultConnection(string $name): void
    {
        self::$defaultConnection = $name;
    }

    public static function getDefaultConnectionName(): string
    {
        return self::$defaultConnection;
    }

    /**
     * Get the registered connection names (configs, not necessarily open drivers).
     *
     * @return array<int, string>
     */
    public static function getConnections(): array
    {
        return array_keys(self::$connections);
    }

    /**
     * Get the names of currently open (instantiated) drivers.
     *
     * @return array<int, string>
     */
    public static function getOpenConnections(): array
    {
        return array_keys(self::$drivers);
    }

    /**
     * Resolve a connection by name. Builds the driver lazily on first use.
     */
    public static function connection(string $name = null): DatabaseDriverInterface
    {
        $name = $name ?? self::$defaultConnection;

        if (!isset(self::$drivers[$name])) {
            if (!isset(self::$connections[$name])) {
                throw new \Exception("Database connection '{$name}' not found");
            }

            $config = self::$connections[$name];
            $driver = self::createDriver($config['driver'] ?? 'mysql');
            $driver->connect($config);

            self::$drivers[$name] = $driver;
        }

        return self::$drivers[$name];
    }

    public static function table(string $table): QueryBuilder
    {
        return (new QueryBuilder())->from($table);
    }

    /**
     * Register a callback invoked on every query the drivers execute.
     *
     * Signature: function(string $sql, array $params, DatabaseDriverInterface $driver): void
     *
     * Listeners are called synchronously inside the query path; keep them fast
     * (push to a buffer/log/queue, don't do I/O inline).
     */
    public static function listen(callable $callback): void
    {
        self::$listeners[] = $callback;
    }

    /**
     * Remove all registered listeners. Mainly for tests and worker resets.
     */
    public static function clearListeners(): void
    {
        self::$listeners = [];
    }

    /**
     * Invoked by drivers right before a prepared statement executes.
     * No-op when no listeners are registered.
     */
    public static function emitListener(string $sql, array $params, DatabaseDriverInterface $driver): void
    {
        if (empty(self::$listeners)) {
            return;
        }
        foreach (self::$listeners as $listener) {
            try {
                $listener($sql, $params, $driver);
            } catch (\Throwable $e) {
                // A buggy listener must not break the query.
                error_log("DB::listen callback threw: " . $e->getMessage());
            }
        }
    }

    /**
     * Build a driver instance for a given driver-type string.
     */
    protected static function createDriver(string $type): DatabaseDriverInterface
    {
        switch (strtolower($type)) {
            case 'mysql':
                return new MySQLDriver();
            case 'pgsql':
            case 'postgres':
            case 'postgresql':
                return new PostgreSQLDriver();
            case 'sqlite':
                return new SQLiteDriver();
            case 'mongodb':
            case 'mongo':
                return new MongoDBDriver();
            case 'sqlsrv':
            case 'sqlserver':
                return new SQLServerDriver();
            case 'null':
                return new NullDriver();
            default:
                throw new \Exception("Unsupported database driver: {$type}");
        }
    }

    /**
     * Forward unknown static calls to the default connection's driver.
     * Use connection($name) explicitly for any other connection.
     *
     * Return types:
     *   DB::table('users')           → QueryBuilder (explicit static)
     *   DB::query('SELECT 1', [])    → whatever the driver returns (via __callStatic)
     *   DB::insert('t', $data)       → driver-specific return (via __callStatic)
     *
     * The explicit statics below (beginTransaction/commit/rollBack) exist
     * purely because they need void return types the interface enforces;
     * everything else is driver-method-pass-through.
     */
    public static function __callStatic(string $method, array $args)
    {
        return self::connection()->{$method}(...$args);
    }

    public static function beginTransaction(): void
    {
        self::connection()->beginTransaction();
    }

    public static function commit(): void
    {
        self::connection()->commit();
    }

    public static function rollBack(): void
    {
        self::connection()->rollBack();
    }

    /**
     * Run $callback inside a transaction. Commits on success; rolls back on any
     * exception and rethrows. Use the return value of $callback as the result.
     */
    public static function transaction(callable $callback)
    {
        self::beginTransaction();
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }
}
