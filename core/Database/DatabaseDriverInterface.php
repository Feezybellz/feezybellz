<?php

namespace Framework\Core\Database;

interface DatabaseDriverInterface
{
    public function connect(array $config): void;
    
    public function query(string $query, array $params = []);
    
    /**
     * Interpret and execute a QueryBuilder instance
     * * @param QueryBuilder $builder
     * @return mixed
     */
    public function executeBuilder(QueryBuilder $builder); //

    public function insert(string $table, array $data);
    public function update(string $table, array $data, array $where);
    public function delete(string $table, array $where);
    public function lastInsertId();
    public function isConnected(): bool;
    
    /**
     * Create the underlying storage (Table for SQL, Collection for NoSQL)
     * * @param Schema $schema The blueprint object containing column/index metadata
     */
    public function createStorage(Schema $schema): void;

    /**
     * Alter the underlying storage.
     */
    public function alterStorage(Schema $schema): void;

    /**
     * Drop the underlying storage
     */
    public function dropStorage(string $name): void;

    /**
     * Ensure the framework's migration tracking exists
     */
    public function ensureMigrationTracking(string $tableName): void;
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollBack(): void;
    public function inTransaction(): bool;

    /**
     * Get the driver's specific grammar
     */
    public function getGrammar(): Grammar;
}