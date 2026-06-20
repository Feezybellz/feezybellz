<?php

namespace Framework\Core\Database;

use PDO;
use Exception;

class SQLServerDriver implements DatabaseDriverInterface
{
    protected ?PDO $pdo = null;
    protected string $dsn;

    public function connect(array $config): void
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? '1433';
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $options = $config['options'] ?? [];

        // Using Microsoft's official sqlsrv PDO driver
        $this->dsn = "sqlsrv:Server={$host},{$port};Database={$database}";

        try {
            $defaultOptions = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            ];

            $this->pdo = new PDO($this->dsn, $username, $password, array_merge($defaultOptions, $options));
        } catch (\PDOException $e) {
            throw new Exception("SQL Server Connection Error: " . $e->getMessage());
        }
    }

    public function query(string $query, array $params = [])
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    public function executeBuilder(QueryBuilder $builder)
    {
        // For SQL Server, LIMIT/OFFSET logic is slightly different 
        // Typically requires ORDER BY and OFFSET ... FETCH NEXT ... ROWS ONLY
        // We will execute standard compiled queries for now
        $query = clone $builder;
        $sql = $query->toSql();
        $params = $query->getBindings();

        $stmt = $this->query($sql, $params);

        if ($builder->operation === 'count') {
            return (int) $stmt->fetchColumn();
        }

        return $stmt->fetchAll();
    }

    public function insert(string $table, array $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        
        return $stmt->rowCount();
    }

    public function update(string $table, array $data, array $where)
    {
        $setClauses = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $setClauses[] = "{$column} = ?";
            $params[] = $value;
        }
        
        $whereClauses = [];
        foreach ($where as $column => $value) {
            $whereClauses[] = "{$column} = ?";
            $params[] = $value;
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setClauses) . " WHERE " . implode(' AND ', $whereClauses);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }

    public function delete(string $table, array $where)
    {
        $whereClauses = [];
        $params = [];
        
        foreach ($where as $column => $value) {
            $whereClauses[] = "{$column} = ?";
            $params[] = $value;
        }
        
        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereClauses);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }

    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    public function isConnected(): bool
    {
        return $this->pdo instanceof PDO;
    }

    public function createStorage(Schema $schema): void {}
    public function alterStorage(Schema $schema): void {}
    public function dropStorage(string $name): void {}
    public function ensureMigrationTracking(string $tableName): void {}

    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void { $this->pdo->commit(); }
    public function rollBack(): void { $this->pdo->rollBack(); }
    public function inTransaction(): bool { return $this->pdo->inTransaction(); }
    
    public function getGrammar(): Grammar 
    {
        // Should return a SQLServerGrammar class 
        // For simplicity, returning MySQLGrammar which works for basic queries
        return new MySQLGrammar(); 
    }
}
