<?php

namespace Framework\Core\Database;

abstract class Migration
{
    protected $db;
    
    public function __construct()
    {
        $this->db = DB::connection();
    }

    /**
     * Set the database connection for the migration
     */
    public function setConnection(string $name): self
    {
        $this->db = DB::connection($name);
        return $this;
    }

    abstract public function up(): void;
    abstract public function down(): void;
    
    /**
     * Create a table/collection
     */
    protected function createTable(string $table, callable $callback): void
    {
        $schema = new Schema($table, $this->db);
        $callback($schema);
        $schema->create();
    }

    /**
     * Alter an existing table
     */
    protected function alterTable(string $table, callable $callback): void
    {
        $schema = new Schema($table, $this->db, true);
        $callback($schema);
        $schema->alter();
    }

    /**
     * Alias for alterTable to match standard framework conventions
     */
    protected function table(string $table, callable $callback): void
    {
        $this->alterTable($table, $callback);
    }

    protected function dropTable(string $table): void
    {
        $this->db->dropStorage($table);
    }
}
