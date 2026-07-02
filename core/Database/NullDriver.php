<?php

namespace Framework\Core\Database;

/**
 * No-op driver. Every method returns a benign value (1, [], etc.) so code
 * paths that touch the DB during a unit test don't have to be mocked.
 *
 * Activate by setting a connection's driver to "null":
 *   DB::addConnection('default', ['driver' => 'null']);
 */
class NullDriver implements DatabaseDriverInterface
{
    public function connect(array $config): void {}

    public function disconnect(): void {}

    public function query(string $query, array $params = [])
    {
        return new \stdClass();
    }

    public function executeBuilder(QueryBuilder $builder)
    {
        if ($builder->operation === 'count') return 0;
        if (in_array($builder->operation, ['sum', 'avg'], true)) return 0.0;
        if (in_array($builder->operation, ['insert', 'update', 'delete'], true)) return 1;
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

    public function getGrammar(): Grammar
    {
        return new MySQLGrammar();
    }
}
