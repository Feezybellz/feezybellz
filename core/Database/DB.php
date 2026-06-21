<?php

namespace Framework\Core\Database;

class DB
{
    protected static $connections = [];
    protected static $drivers = [];
    protected static $defaultConnection = 'default';
    
    /**
     * Initialize a database connection
     * 
     * @param string $name Connection name
     * @param array $config Connection configuration
     * @return void
     */
    public static function addConnection(string $name, array $config): void
    {
        self::$connections[$name] = $config;
        if (isset(self::$drivers[$name])) {
            unset(self::$drivers[$name]);
        }
    }

    /**
     * Set the default connection name
     */
    public static function setDefaultConnection(string $name): void
    {
        self::$defaultConnection = $name;
    }

    /**
     * Get the default connection name
     */
    public static function getDefaultConnectionName(): string
    {
        return self::$defaultConnection;
    }
    
    /**
     * Get a database connection
     * 
     * @param string $name Connection name (default: null uses self::$defaultConnection)
     * @return DatabaseDriverInterface
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
                return new class implements DatabaseDriverInterface {
                    public function connect(array $config): void {}
                    public function query(string $query, array $params = []) { return new \stdClass(); }
                    public function executeBuilder(QueryBuilder $builder) { 
                        if ($builder->operation === 'count') return 0;
                        return []; 
                    }
                    public function insert(string $table, array $data) { return 1; }
                    public function update(string $table, array $data, array $where) { return 1; }
                    public function delete(string $table, array $where) { return 1; }
                    public function lastInsertId() { return 1; }
                    public function isConnected(): bool { return true; }
                    public function createStorage(Schema $schema): void {}
                    public function alterStorage(Schema $schema): void {}
                    public function dropStorage(string $name): void {}
                    public function ensureMigrationTracking(string $tableName): void {}
                    public function beginTransaction(): void {}
                    public function commit(): void {}
                    public function rollBack(): void {}
                    public function inTransaction(): bool { return false; }
                    public function getGrammar(): Grammar { return new MySQLGrammar(); }
                };
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
