<?php

namespace Framework\Core\Database;

class Migrator
{
    protected $migrationsPath;
    protected $migrationsTable = 'migrations';
    
    public function __construct(string $migrationsPath = null)
    {
        if ($migrationsPath === null) {
            $cwd = getcwd();
            $this->migrationsPath = is_dir($cwd . '/database/migrations') 
                ? $cwd . '/database/migrations' 
                : dirname(__DIR__, 2) . '/database/migrations';
        } else {
            $this->migrationsPath = $migrationsPath;
        }
        
        $this->ensureMigrationsTable();
    }

    public function run(): array
    {
        $migrated = [];
        $files = $this->getMigrationFiles();
        $executed = $this->getExecutedMigrations();
        
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $executed)) continue;

            $migration = require $file;
            if (is_object($migration)) {
                $instance = $migration;
            } else {
                $className = $this->getClassName($name);
                $instance = new $className();
            }
            
            $instance->up();
            $this->recordMigration($name);
            $migrated[] = $name;
        }
        return $migrated;
    }

    /**
     * Rollback migrations by a specific number of steps
     */
    public function rollback(int $steps = 1): array
    {
        $rolledBack = [];
        
        // POLYGLOT: Get the last N executed migrations using QueryBuilder
        $executed = DB::table($this->migrationsTable)
            ->orderBy('id', 'DESC')
            ->limit($steps)
            ->get();

        foreach ($executed as $record) {
            $name = $record['migration'];
            $file = $this->migrationsPath . '/' . $name . '.php';
            
            if (file_exists($file)) {
                $migration = require $file;
                if (is_object($migration)) {
                    $instance = $migration;
                } else {
                    $className = $this->getClassName($name);
                    $instance = new $className();
                }
                
                $instance->down();
                $this->removeMigration($name);
                $rolledBack[] = $name;
            }
        }
        return $rolledBack;
    }

    protected function getExecutedMigrations(): array
    {
        $results = DB::table($this->migrationsTable)
            ->select(['migration'])
            ->orderBy('id', 'ASC')
            ->get();

        return array_column($results, 'migration');
    }

    protected function recordMigration(string $name): void
    {
        DB::table($this->migrationsTable)->insert([
            'migration' => $name,
            'batch' => $this->getNextBatchNumber(),
        ]);
    }

    protected function removeMigration(string $name): void
    {
        DB::table($this->migrationsTable)
            ->where('migration', '=', $name)
            ->delete();
    }

    protected function getNextBatchNumber(): int
    {
        return DB::table($this->migrationsTable)->count() + 1;
    }

    protected function ensureMigrationsTable(): void
    {
        // Delegated to driver to handle SQL vs NoSQL tracking setup
        DB::connection()->ensureMigrationTracking($this->migrationsTable);
    }

    protected function getMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) return [];
        $files = glob($this->migrationsPath . '/*.php');
        sort($files);
        return $files;
    }

    protected function getClassName(string $filename): string
    {
        $name = preg_replace('/^\d{14}_/', '', $filename);
        return str_replace('_', '', ucwords($name, '_'));
    }
}
