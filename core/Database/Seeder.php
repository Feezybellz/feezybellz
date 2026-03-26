<?php

namespace Framework\Core\Database;

abstract class Seeder
{
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
        return DB::table($table)->insert($data);
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

        $builder = DB::table($table);
        $builder->operation = 'insert';
        $builder->data = $rows;

        return (bool)DB::connection()->executeBuilder($builder);
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
        $builder = DB::table($table);
        $builder->operation = 'update';
        $builder->data = $data;
        
        // Add the unique identification to the builder for the driver to handle
        foreach ($uniqueBy as $column) {
            if (isset($data[$column])) {
                $builder->where($column, '=', $data[$column]);
            }
        }

        // The driver executeBuilder handles the native upsert logic
        return (bool)DB::connection()->executeBuilder($builder);
    }

    protected function batchUpsert(string $table, array $rows, array $uniqueBy): bool
    {
        if (empty($rows)) return false;

        $builder = DB::table($table);
        $builder->operation = 'upsert'; // New operation type
        $builder->data = $rows;
        $builder->uniqueBy = $uniqueBy; // Tell the driver which columns to check

        return (bool)DB::connection()->executeBuilder($builder);
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
        $seeder->run();
        $this->success("Seeded: {$seederClass}");
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