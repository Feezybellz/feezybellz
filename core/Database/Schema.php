<?php

namespace Framework\Core\Database;

/**
 * Polyglot Schema Builder
 * * This class collects structural metadata about a table/collection.
 * It does not contain any raw SQL strings. The translation to SQL or 
 * NoSQL happens exclusively inside the Database Drivers.
 */
class Schema
{
    public $table;
    protected $db;
    
    // Metadata storage
    public $columns = [];
    public $indexes = [];
    public $uniqueIndexes = [];
    public $primaryKey = null;
    public $foreignKeys = [];
    
    public $isAlter = false;
    public $droppedColumns = [];

    
    public function __construct(string $table, DatabaseDriverInterface $db, bool $isAlter = false)
    {
        $this->table = $table;
        $this->db = $db;
        $this->isAlter = $isAlter;
    }

    /**
     * Add a column definition to the metadata store
     */
    protected function addColumn(string $name, string $type, array $options = []): self
    {
        $this->columns[] = array_merge([
            'name' => $name,
            'type' => $type,
            'nullable' => false,
            'default' => null,
            'unique' => false,
            'length' => null,
            'precision' => null,
            'scale' => null,
            'allowed' => [] // For enums
        ], $options);

        return $this;
    }

    // =========================================================
    // DATA TYPES (Polyglot Blueprints)
    // =========================================================

    public function id(string $name = 'id'): self
    {
        $this->primaryKey = $name;
        return $this->addColumn($name, 'id', ['auto_increment' => true]);
    }

    public function string(string $name, int $length = 255): self
    {
        return $this->addColumn($name, 'string', ['length' => $length]);
    }

    public function text(string $name): self
    {
        return $this->addColumn($name, 'text');
    }

    public function mediumText(string $name): self
    {
        return $this->addColumn($name, 'mediumtext');
    }

    public function longText(string $name): self
    {
        return $this->addColumn($name, 'longtext');
    }

    public function tinyText(string $name): self
    {
        return $this->addColumn($name, 'tinytext');
    }

    public function integer(string $name): self
    {
        return $this->addColumn($name, 'integer');
    }

    public function tinyint(string $name): self
    {
        return $this->addColumn($name, 'tinyint');
    }

    public function bigInteger(string $name): self
    {
        return $this->addColumn($name, 'big_integer');
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2): self
    {
        return $this->addColumn($name, 'decimal', ['precision' => $precision, 'scale' => $scale]);
    }

    public function boolean(string $name): self
    {
        return $this->addColumn($name, 'boolean');
    }

    public function date(string $name): self
    {
        return $this->addColumn($name, 'date');
    }

    public function datetime(string $name): self
    {
        return $this->addColumn($name, 'datetime');
    }

    public function timestamp(string $name): self
    {
        return $this->addColumn($name, 'timestamp');
    }

    public function time(string $name): self
    {
        return $this->addColumn($name, 'time');
    }

    // Inside Schema.php

    public function timestamps(): self
    {
        // Pass the keywords without quotes; the driver will handle the MySQL specific syntax
        $this->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP']);
        $this->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP_ON_UPDATE']);
        return $this;
    }

    public function json(string $name): self
    {
        return $this->addColumn($name, 'json');
    }

    public function enum(string $name, array $values): self
    {
        return $this->addColumn($name, 'enum', ['allowed' => $values]);
    }

    // =========================================================
    // ALTERATIONS
    // =========================================================

    public function dropColumn(string $name): self
    {
        $this->droppedColumns[] = $name;
        return $this;
    }

    public function after(string $column): self
    {
        if (!empty($this->columns)) {
            $this->columns[count($this->columns) - 1]['after'] = $column;
        }
        return $this;
    }

    // =========================================================
    // MODIFIERS (Fluent API)
    // =========================================================

    public function modify(): self
    {
        $this->columns[count($this->columns) - 1]['modify'] = true;
        return $this;
    }

    public function nullable(): self
    {
        $this->columns[count($this->columns) - 1]['nullable'] = true;
        return $this;
    }

    public function default($value): self
    {
        $this->columns[count($this->columns) - 1]['default'] = $value;
        return $this;
    }

    public function unique(): self
    {
        $this->columns[count($this->columns) - 1]['unique'] = true;
        return $this;
    }

    public function unsigned(): self
    {
        $this->columns[count($this->columns) - 1]['unsigned'] = true;
        return $this;
    }

    public function primary(string $column = null): self
    {
        if ($column === null && !empty($this->columns)) {
            $this->primaryKey = $this->columns[count($this->columns) - 1]['name'];
        } else {
            $this->primaryKey = $column;
        }
        return $this;
    }

    /**
     * Add a comment to the column
     */
    public function comment(string $comment): self
    {
        if (!empty($this->columns)) {
            $this->columns[count($this->columns) - 1]['comment'] = $comment;
        }
        return $this;
    }

    // =========================================================
    // INDEXING
    // =========================================================

    public function index($columns = null, string $name = null): self
    {
        if ($columns === null && !empty($this->columns)) {
            $columns = [$this->columns[count($this->columns) - 1]['name']];
        }
        
        $columns = is_array($columns) ? $columns : [$columns];
        $this->indexes[] = [
            'name' => $name ?? 'idx_' . $this->table . '_' . implode('_', $columns),
            'columns' => $columns
        ];
        return $this;
    }

    public function uniqueIndex($columns = null, string $name = null): self
    {
        if ($columns === null && !empty($this->columns)) {
            $columns = [$this->columns[count($this->columns) - 1]['name']];
        }

        $columns = is_array($columns) ? $columns : [$columns];
        $this->uniqueIndexes[] = [
            'name' => $name ?? 'uniq_' . $this->table . '_' . implode('_', $columns),
            'columns' => $columns
        ];
        return $this;
    }


    // =========================================================
    // RELATIONSHIPS & CONSTRAINTS
    // =========================================================

    /**
     * Shortcut for creating an unsigned big integer column for a foreign key
     */
    public function foreignId(string $column): self
    {
        // Most modern frameworks use Big Interger for IDs by default
        return $this->addColumn($column, 'big_integer', ['unsigned' => true]);
    }

    /**
     * Define the relationship for the last added column
     */
    public function constrained(string $table = null, string $column = 'id'): self
    {
        $lastColumn = $this->columns[count($this->columns) - 1]['name'];
        
        // If table isn't provided, we can try to guess it from the column name
        // e.g., 'user_id' -> 'users'
        if ($table === null) {
            $table = str_replace('_id', 's', $lastColumn);
        }

        $this->foreignKeys[] = [
            'column' => $lastColumn,
            'references' => $column,
            'on' => $table,
            'onDelete' => 'RESTRICT', // Default safety
            'onUpdate' => 'CASCADE'
        ];

        return $this;
    }

    /**
     * Set the ON DELETE action for the most recent foreign key
     */
    public function onDelete(string $action): self
    {
        if (!empty($this->foreignKeys)) {
            $this->foreignKeys[count($this->foreignKeys) - 1]['onDelete'] = strtoupper($action);
        }
        return $this;
    }

    /**
     * Set the ON UPDATE action for the most recent foreign key
     */
    public function onUpdate(string $action): self
    {
        if (!empty($this->foreignKeys)) {
            $this->foreignKeys[count($this->foreignKeys) - 1]['onUpdate'] = strtoupper($action);
        }
        return $this;
    }

    /**
     * Quick helper for the common 'cascade on delete' pattern
     */
    public function cascadeOnDelete(): self
    {
        return $this->onDelete('CASCADE');
    }

    // =========================================================
    // EXECUTION
    // =========================================================

    /**
     * Hand over the collected blueprint to the active driver for creation.
     */
    public function create(): void
    {
        $this->db->createStorage($this);
    }

    /**
     * Hand over the collected blueprint to the active driver for alteration.
     */
    public function alter(): void
    {
        $this->db->alterStorage($this);
    }
}
