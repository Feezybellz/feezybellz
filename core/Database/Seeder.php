<?php

namespace Framework\Core\Database;

abstract class Seeder
{
    protected $connection = 'default';

    /**
     * Set the database connection to use
     * 
     * @param string $connection
     * @return void
     */
    public function setConnection(string $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * Run the seeder logic.
     * * @return void
     */
    abstract public function run(): void;

    /**
     * Insert a single row into a table/collection.
     * Uses the QueryBuilder to ensure engine-agnostic (Polyglot) support.
     * * @param string $table
     * @param array $data
     * @return bool
     */
    protected function insert(string $table, array $data): bool
    {
        return DB::table($table)->on($this->connection)->insert($data);
    }

    /**
     * Optimized batch insert for large datasets (e.g., 1 million rows).
     * This passes the entire array to the driver to use native bulk operations.
     * * @param string $table
     * @param array $rows Multi-dimensional array of records
     * @return bool
     */
    protected function batchInsert(string $table, array $rows): bool
    {
        if (empty($rows)) {
            return false;
        }

        $builder = DB::table($table)->on($this->connection);
        $builder->operation = 'insert';
        $builder->data = $rows;

        return (bool)DB::connection($this->connection)->executeBuilder($builder);
    }

    /**
     * Upsert records (Update if exists, Insert if not).
     * MySQL uses 'ON DUPLICATE KEY UPDATE'; MongoDB uses 'upsert' flag.
     * * @param string $table
     * @param array $data
     * @param array $uniqueBy The columns that define a unique record (e.g., ['product_code'])
     * @return bool
     */
    protected function upsert(string $table, array $data, array $uniqueBy): bool
    {
        $builder = DB::table($table)->on($this->connection);
        $builder->operation = 'update';
        $builder->data = $data;
        
        // Add the unique identification to the builder for the driver to handle
        foreach ($uniqueBy as $column) {
            if (isset($data[$column])) {
                $builder->where($column, '=', $data[$column]);
            }
        }

        // The driver executeBuilder handles the native upsert logic
        return (bool)DB::connection($this->connection)->executeBuilder($builder);
    }

    protected function batchUpsert(string $table, array $rows, array $uniqueBy): bool
    {
        if (empty($rows)) return false;

        $builder = DB::table($table)->on($this->connection);
        $builder->operation = 'upsert'; // New operation type
        $builder->data = $rows;
        $builder->uniqueBy = $uniqueBy; // Tell the driver which columns to check

        return (bool)DB::connection($this->connection)->executeBuilder($builder);
    }
    /**
     * Call another seeder.
     * * @param string $seederClass Fully qualified class name
     * @return void
     */
    protected function call(string $seederClass): void
    {
        if (!class_exists($seederClass)) {
            $seedersPath = $this->getSeedersPath();
            $file = $seedersPath . '/' . $seederClass . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }

        if (!class_exists($seederClass)) {
            $this->error("Seeder class not found: {$seederClass}");
            return;
        }

        $this->info("Seeding: {$seederClass}");
        $seeder = new $seederClass();
        if (method_exists($seeder, 'setConnection')) {
            $seeder->setConnection($this->connection);
        }
        $seeder->run();
        $this->success("Seeded: {$seederClass}");
    }

    /**
     * Seed from a raw SQL file using the MySQL CLI (fastest and safest for large files).
     */
    protected function seedFromSQL(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            $this->error("SQL file not found: {$filePath}");
            return false;
        }

        $connections = DB::getConnections();
        $connConfig = $connections[$this->connection] ?? null;

        if ($connConfig) {
            $driver = $connConfig['driver'] ?? 'mysql';
            $host = $connConfig['host'] ?? '127.0.0.1';
            $user = $connConfig['username'] ?? 'root';
            $pass = $connConfig['password'] ?? '';
            $db   = $connConfig['database'] ?? null;
            $port = $connConfig['port'] ?? 3306;
        } else {
            // Fallback to env
            $driver = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: 'mysql';
            $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
            $user = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root';
            $pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
            $db   = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE');
            $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306;
        }

        if (!$db) {
            $this->error("Database name is not set in the environment or config.");
            return false;
        }

        switch ($driver) {
            case 'mysql':
                $command = sprintf(
                    'mysql -h %s -P %s -u %s %s %s < %s',
                    escapeshellarg($host),
                    escapeshellarg($port),
                    escapeshellarg($user),
                    $pass ? '-p' . escapeshellarg($pass) : '',
                    escapeshellarg($db),
                    escapeshellarg($filePath)
                );
                break;
            case 'pgsql':
            case 'postgresql':
                $command = sprintf(
                    'PGPASSWORD=%s psql -h %s -p %s -U %s -d %s -f %s',
                    escapeshellarg($pass),
                    escapeshellarg($host),
                    escapeshellarg($port),
                    escapeshellarg($user),
                    escapeshellarg($db),
                    escapeshellarg($filePath)
                );
                break;
            case 'sqlite':
                if ($db === ':memory:') {
                    $this->error("Cannot seed SQL file into in-memory SQLite database via CLI.");
                    return false;
                }
                $command = sprintf('sqlite3 %s < %s', escapeshellarg($db), escapeshellarg($filePath));
                break;
            case 'sqlsrv':
            case 'sqlserver':
                $command = sprintf(
                    'sqlcmd -S %s,%s -U %s -P %s -d %s -i %s',
                    escapeshellarg($host),
                    escapeshellarg($port),
                    escapeshellarg($user),
                    escapeshellarg($pass),
                    escapeshellarg($db),
                    escapeshellarg($filePath)
                );
                break;
            case 'mongodb':
                $this->error("Cannot import a .sql file into MongoDB. Use seedFromJSON() or seedFromMongoRestore() for MongoDB databases instead.");
                return false;
            default:
                $this->error("Driver [{$driver}] does not support raw SQL file seeding via CLI.");
                return false;
        }

        $this->info("Importing SQL file into [{$driver}: {$db}]: {$filePath} ...");
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            $this->error("Failed to import SQL file. Make sure the mysql CLI tool is installed.");
            return false;
        }

        $this->success("Successfully imported: {$filePath} into [{$driver}: {$db}]");
        return true;
    }

    /**
     * Seed MongoDB from a JSON or CSV file using the mongoimport CLI tool.
     */
    protected function seedFromMongoJSON(string $filePath, string $collection, string $type = 'json', bool $jsonArray = true): bool
    {
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return false;
        }

        $connections = DB::getConnections();
        $connConfig = $connections[$this->connection] ?? null;

        $host = $connConfig['host'] ?? $_ENV['MONGO_HOST'] ?? '127.0.0.1';
        $port = $connConfig['port'] ?? $_ENV['MONGO_PORT'] ?? 27017;
        $user = $connConfig['username'] ?? $_ENV['MONGO_USERNAME'] ?? '';
        $pass = $connConfig['password'] ?? $_ENV['MONGO_PASSWORD'] ?? '';
        $db   = $connConfig['database'] ?? $_ENV['MONGO_DATABASE'] ?? null;
        $auth = $connConfig['options']['authSource'] ?? $_ENV['MONGO_AUTH_SOURCE'] ?? 'admin';

        if (!$db) {
            $this->error("MongoDB database name is not set.");
            return false;
        }

        // Build the mongoimport command
        $command = sprintf(
            'mongoimport --host %s --port %s --db %s --collection %s --type %s --file %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($db),
            escapeshellarg($collection),
            escapeshellarg($type),
            escapeshellarg($filePath)
        );

        if ($user) {
            $command .= sprintf(
                ' --username %s --password %s --authenticationDatabase %s',
                escapeshellarg($user),
                escapeshellarg($pass),
                escapeshellarg($auth)
            );
        }

        if ($type === 'json' && $jsonArray) {
            $command .= ' --jsonArray';
        }

        $this->info("Importing MongoDB file into [{$db}.{$collection}]: {$filePath} ...");
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            $this->error("Failed to import MongoDB file. Make sure 'mongoimport' is installed.");
            return false;
        }

        $this->success("Successfully imported: {$filePath} into [{$db}.{$collection}]");
        return true;
    }

    /**
     * Call multiple seeders.
     * * @param array $seederClasses
     * @return void
     */
    protected function callMany(array $seederClasses): void
    {
        foreach ($seederClasses as $seederClass) {
            $this->call($seederClass);
        }
    }

    // Helper methods for CLI output
    protected function success(string $message): void { echo "\033[32m{$message}\033[0m\n"; }
    protected function error(string $message): void { echo "\033[31mError: {$message}\033[0m\n"; }
    protected function info(string $message): void { echo "\033[36m{$message}\033[0m\n"; }
    protected function warn(string $message): void { echo "\033[33m{$message}\033[0m\n"; }
    protected function line(string $message): void { echo "{$message}\n"; }

    protected function getSeedersPath(): string
    {
        return dirname(dirname(__DIR__)) . '/database/seeders';
    }
}