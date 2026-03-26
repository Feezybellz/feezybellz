<?php

namespace Framework\Core\Database;

abstract class Migration
{
    protected $db;
    
    public function __construct()
    {
        $this->db = DB::connection();
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

    protected function dropTable(string $table): void
    {
        $this->db->dropStorage($table);
    }
}
