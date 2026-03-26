<?php

namespace Framework\Core\Database;

class DB
{
    protected static $connections = [];
    protected static $drivers = [];
    
    /**
     * Initialize a database connection
     * 
     * @param string $name Connection name
     * @param array $config Connection configuration
     * @return void
     */
    public static function addConnection(string $name, array $config): void
    {
        $driver = self::createDriver($config['driver'] ?? 'mysql');
        $driver->connect($config);
        
        self::$drivers[$name] = $driver;
        self::$connections[$name] = $config;
    }
    
    /**
     * Get a database connection
     * 
     * @param string $name Connection name (default: 'default')
     * @return DatabaseDriverInterface
     */
    public static function connection(string $name = 'default'): DatabaseDriverInterface
    {
        if (!isset(self::$drivers[$name])) {
            throw new \Exception("Database connection '{$name}' not found");
        }
        
        return self::$drivers[$name];
    }
    
    public static function table(string $table): QueryBuilder
{
    return (new QueryBuilder())->from($table);
}
    /**
     * Create a driver instance
     * 
     * @param string $type Driver type
     * @return DatabaseDriverInterface
     */
    protected static function createDriver(string $type): DatabaseDriverInterface
    {
        switch (strtolower($type)) {
            case 'mysql':
                return new MySQLDriver();
            case 'mongodb':
            case 'mongo':
                return new MongoDBDriver();
            default:
                throw new \Exception("Unsupported database driver: {$type}");
        }
    }
    
    /**
     * Magic method to forward static calls to the default connection
     * 
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic(string $method, array $args)
    {
        return self::connection()->{$method}(...$args);
    }

    protected static $transactionCount = 0;

    public static function beginTransaction(): void 
    { 
        if (self::$transactionCount === 0) {
            self::connection()->beginTransaction(); 
        }
        self::$transactionCount++;
    }

    public static function commit(): void 
    { 
        self::$transactionCount--;
        if (self::$transactionCount === 0) {
            self::connection()->commit(); 
        }
    }

    public static function rollBack(): void 
    { 
        if (self::$transactionCount > 0) {
            self::connection()->rollBack(); 
            self::$transactionCount = 0; // Reset on rollback as DB transaction is closed
        }
    }

    /**
     * Transaction wrapper for automatic handling
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
