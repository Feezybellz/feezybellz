<?php

namespace Framework\Core\Database;

use PDO;
use PDOException;

class MySQLDriver implements DatabaseDriverInterface
{
    protected $config = [];
    protected $connection = null;
    protected $grammar = null;
    protected $transactionDepth = 0;

    /**
     * Column-list cache per table.
     * Populated on first `unselect(...)`-with-`*` lookup, reused across
     * subsequent SELECTs. Cleared on disconnect().
     * @var array<string, array<int, string>>
     */
    protected array $columnCache = [];

    public function getGrammar(): Grammar
    {
        if (!$this->grammar) {
            $this->grammar = new MySQLGrammar();
        }
        return $this->grammar;
    }
    
    public function connect(array $config): void
    {
        $this->config = $config;
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
        try {
            $this->connection = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Drop the underlying PDO so the next operation reconnects.
     * Called by DB::purge() during dynamic tenant connection swaps to
     * ensure no file descriptors leak across requests in long-running workers.
     */
    public function disconnect(): void
    {
        $this->connection = null;
        $this->transactionDepth = 0;
        $this->columnCache = [];
    }

    private function isConnectionLost(PDOException $e): bool
    {
        $code = $e->getCode();
        $message = $e->getMessage();
        return in_array($code, ['HY000', '2006', '2013', '08S01', '08006']) || strpos($message, 'server has gone away') !== false;
    }

    private function reconnect(): void
    {
        if (!empty($this->config)) {
            $this->connect($this->config);
        }
    }

    public function query(string $query, array $params = [])
    {
        try {
            return $this->executeQuery($query, $params);
        } catch (PDOException $e) {
            if ($this->isConnectionLost($e)) {
                $this->reconnect();
                return $this->executeQuery($query, $params);
            }
            error_log("Database Error: " . $e->getMessage() . " | Query: " . $query);
            throw new \Exception("Database Error: " . $e->getMessage());
        }
    }

    private function executeQuery(string $query, array $params = [])
    {
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $params[$key] = $value ? 1 : 0;
            }
        }
        $statement = $this->connection->prepare($query);
        DB::emitListener($query, $params, $this);
        $statement->execute($params);
        return $statement;
    }

    public function executeBuilder(QueryBuilder $builder)
    {
        switch ($builder->operation) {
            case 'select':
                return $this->handleSelect($builder);
            case 'insert':
                return $this->handleInsert($builder);
            case 'update':
                return $this->handleUpdate($builder);
            case 'upsert':
                return $this->handleUpsert($builder);
            case 'delete':
                return $this->handleDelete($builder);
            case 'count':
                return $this->handleCount($builder);
            case 'sum':
                return $this->handleSum($builder);
            case 'avg':
                return $this->handleAvg($builder);
            case 'raw':
                return $this->handleRaw($builder);
            case 'increment':
                return $this->handleIncrement($builder, '+');
            case 'decrement':
                return $this->handleIncrement($builder, '-');
            default:
                throw new \Exception("Unsupported builder operation: {$builder->operation}");
        }
    }

    private function handleCount(QueryBuilder $builder): int
    {
        $params = [];
        $sql = "SELECT COUNT(*) as aggregate FROM " . $this->getGrammar()->wrapTable($builder->table) . " ";
        $sql .= $this->compileJoins($builder);
        $sql .= $this->compileWhere($builder, $params);
        $res = $this->query($sql, $params)->fetch(\PDO::FETCH_ASSOC);
        return (int) ($res['aggregate'] ?? 0);
    }

    private function handleSum(QueryBuilder $builder): float
    {
        $params = [];
        $column = $this->getGrammar()->wrap($builder->aggregateColumn);
        $sql = "SELECT SUM({$column}) as aggregate FROM " . $this->getGrammar()->wrapTable($builder->table) . " ";
        $sql .= $this->compileWhere($builder, $params);
        $res = $this->query($sql, $params)->fetch(\PDO::FETCH_ASSOC);
        return (float) ($res['aggregate'] ?? 0);
    }

    private function handleAvg(QueryBuilder $builder): float
    {
        $params = [];
        $column = $this->getGrammar()->wrap($builder->aggregateColumn);
        $sql = "SELECT AVG({$column}) as aggregate FROM " . $this->getGrammar()->wrapTable($builder->table) . " ";
        $sql .= $this->compileWhere($builder, $params);
        $res = $this->query($sql, $params)->fetch(\PDO::FETCH_ASSOC);
        return (float) ($res['aggregate'] ?? 0);
    }

    private function handleRaw(QueryBuilder $builder)
    {
        $statement = $this->query($builder->rawSql, $builder->rawParams);
        
        // Improved regex to detect SELECT-like queries even if they start with parentheses or CTEs
        if (preg_match('/^\s*\(?\s*(SELECT|SHOW|DESCRIBE|EXPLAIN|WITH)\b/i', $builder->rawSql)) {
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        return $statement->rowCount();
    }

    private function handleInsert(QueryBuilder $builder)
    {
        $table = $this->getGrammar()->wrapTable($builder->table);
        $data = $builder->data;

        if (empty($data)) {
            return false;
        }

        $isBatch = isset($data[0]) && is_array($data[0]);

        if ($isBatch) {
            $columns = array_keys($data[0]);
            $columnList = $this->wrapColumnList($columns);

            $values = [];
            $params = [];
            foreach ($data as $row) {
                $placeholders = implode(', ', array_fill(0, count($row), '?'));
                $values[] = "({$placeholders})";
                foreach ($columns as $col) {
                    $params[] = $row[$col] ?? null;
                }
            }

            $sql = "INSERT INTO {$table} ({$columnList}) VALUES " . implode(', ', $values);
            $this->query($sql, $params);
            return true;
        }

        $columnList = $this->wrapColumnList(array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columnList}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));

        return $this->lastInsertId();
    }

    /**
     * Validate and wrap a list of column names for INSERT/UPDATE column-lists.
     */
    private function wrapColumnList(array $columns): string
    {
        return implode(', ', array_map(function ($c) {
            return '`' . $this->getGrammar()->validateIdentifier($c) . '`';
        }, $columns));
    }

    private function handleUpsert(QueryBuilder $builder)
    {
        $table = $this->getGrammar()->wrapTable($builder->table);
        $data = $builder->data;
        $uniqueBy = $builder->uniqueBy;

        if (empty($data)) return false;

        $isBatch = isset($data[0]) && is_array($data[0]);
        $rows = $isBatch ? $data : [$data];

        $columns = array_keys($rows[0]);
        $columnList = $this->wrapColumnList($columns);

        $updateParts = [];
        foreach ($columns as $col) {
            if (!in_array($col, $uniqueBy, true)) {
                $wrapped = '`' . $this->getGrammar()->validateIdentifier($col) . '`';
                $updateParts[] = "{$wrapped} = VALUES({$wrapped})";
            }
        }
        $updateSql = implode(', ', $updateParts);

        $values = [];
        $params = [];
        foreach ($rows as $row) {
            $placeholders = implode(', ', array_fill(0, count($row), '?'));
            $values[] = "({$placeholders})";
            foreach ($columns as $col) {
                $params[] = $row[$col] ?? null;
            }
        }

        $sql = "INSERT INTO {$table} ({$columnList}) VALUES " . implode(', ', $values);
        if (!empty($updateSql)) {
            $sql .= " ON DUPLICATE KEY UPDATE {$updateSql}";
        }

        $this->query($sql, $params);
        return true;
    }

    private function handleUpdate(QueryBuilder $builder)
    {
        $table = $this->getGrammar()->wrapTable($builder->table);
        $data = $builder->data;
        $params = [];

        $sets = [];
        foreach ($data as $col => $val) {
            $sets[] = '`' . $this->getGrammar()->validateIdentifier($col) . '` = ?';
            $params[] = $val;
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $sets);
        $sql .= $this->compileWhere($builder, $params);

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    private function handleIncrement(QueryBuilder $builder, string $operator = '+')
    {
        $table = $builder->table;
        $column = $builder->data['column'];
        $amount = (float) $builder->data['amount'];
        $extra = $builder->data['extra'];
        $params = [];
        
        $colWrapped = $this->getGrammar()->wrap($column);
        $sets = ["{$colWrapped} = {$colWrapped} {$operator} {$amount}"];
        
        foreach ($extra as $col => $val) {
            $sets[] = $this->getGrammar()->wrap($col) . " = ?";
            $params[] = $this->getGrammar()->formatDate($val);
        }
        
        $sql = "UPDATE " . $this->getGrammar()->wrap($table) . " SET " . implode(', ', $sets);
        $sql .= $this->compileWhere($builder, $params);
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    private function handleDelete(QueryBuilder $builder)
    {
        $table = $this->getGrammar()->wrapTable($builder->table);
        $params = [];

        $sql = "DELETE FROM {$table}";
        $sql .= $this->compileWhere($builder, $params);

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    private function compileJoins(QueryBuilder $builder): string
    {
        if (empty($builder->joins)) return "";
        
        $sql = "";
        foreach ($builder->joins as $join) {
            $sql .= " {$join['type']} JOIN " . $this->getGrammar()->wrap($join['table']) . " ON " . 
                    $this->getGrammar()->wrap($join['first']) . " {$join['operator']} " . 
                    $this->getGrammar()->wrap($join['second']);
        }
        return $sql;
    }

    private function compileWhere(QueryBuilder $builder, array &$params): string
    {
        if (empty($builder->where)) return "";
        
        $clauses = [];
        foreach ($builder->where as $i => $w) {
            $boolean = ($i === 0) ? "" : " {$w['boolean']} ";

            if (isset($w['type']) && $w['type'] === 'raw') {
                $clauses[] = "{$boolean}{$w['sql']}";
                foreach ($w['params'] as $val) $params[] = $val;
                continue;
            }

            if (isset($w['type']) && $w['type'] === 'nested') {
                $nestedBuilder = new QueryBuilder();
                $w['query']($nestedBuilder);
                $nestedSql = $this->compileWhere($nestedBuilder, $params);
                if (!empty($nestedSql)) {
                    // Strip the " WHERE " part from the nested SQL
                    $nestedSql = substr($nestedSql, 7);
                    $clauses[] = "{$boolean}({$nestedSql})";
                }
                continue;
            }

            $operator = strtoupper($w['operator']);
            $column = $this->getGrammar()->wrap($w['column']);
            
            if ($operator === 'IN' || $operator === 'NOT IN') {
                if (empty($w['value'])) {
                    // MySQL rejects empty IN-lists. Render an always-false/true
                    // clause so the query still compiles and preserves intent.
                    $clauses[] = $operator === 'IN' ? "{$boolean}1=0" : "{$boolean}1=1";
                    continue;
                }
                $placeholders = implode(', ', array_fill(0, count($w['value']), '?'));
                $clauses[] = "{$boolean}{$column} {$operator} ({$placeholders})";
                foreach ($w['value'] as $val) {
                    $params[] = $this->getGrammar()->formatDate($val);
                }
            } elseif ($operator === 'BETWEEN') {
                $clauses[] = "{$boolean}{$column} BETWEEN ? AND ?";
                $params[] = $this->getGrammar()->formatDate($w['value'][0]);
                $params[] = $this->getGrammar()->formatDate($w['value'][1]);
            } elseif ($operator === 'YEAR') {
                $clauses[] = "{$boolean}YEAR({$column}) = ?";
                $params[] = $w['value'];
            } elseif ($operator === 'MONTH') {
                $clauses[] = "{$boolean}MONTH({$column}) = ?";
                $params[] = $w['value'];
            } elseif ($operator === 'DATE') {
                $clauses[] = "{$boolean}{$column} = ?";
                $params[] = $w['value'];
            } elseif (in_array($operator, ['IS NULL', 'IS NOT NULL'])) {
                $clauses[] = "{$boolean}{$column} {$operator}";
            } else {
                $clauses[] = "{$boolean}{$column} {$operator} ?";
                $params[] = $this->getGrammar()->formatDate($w['value']);
            }
        }
        return " WHERE " . implode('', $clauses);
    }

    private function compileHaving(QueryBuilder $builder, array &$params): string
    {
        if (empty($builder->having)) return "";
        
        $clauses = [];
        foreach ($builder->having as $i => $h) {
            $boolean = ($i === 0) ? "" : " {$h['boolean']} ";
            $operator = strtoupper($h['operator']);
            $column = $this->getGrammar()->wrap($h['column']);
            
            $clauses[] = "{$boolean}{$column} {$operator} ?";
            $params[] = $this->getGrammar()->formatDate($h['value']);
        }
        return " HAVING " . implode('', $clauses);
    }

    private function handleSelect(QueryBuilder $builder): array
    {
        $select = $builder->select;

        if (!empty($builder->unselect)) {
            if (count($select) === 1 && $select[0] === '*') {
                // Per-connection cache: SHOW COLUMNS is cheap but pointless
                // to re-run for every unselect(*) SELECT. Invalidated on
                // disconnect() (and any subsequent schema change requires
                // a driver restart anyway).
                $tableName = $this->getGrammar()->validateIdentifier($builder->table);
                if (!isset($this->columnCache[$tableName])) {
                    $stmt = $this->query("SHOW COLUMNS FROM " . $this->getGrammar()->wrapTable($builder->table));
                    $this->columnCache[$tableName] = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                }
                $select = array_diff($this->columnCache[$tableName], $builder->unselect);
            } else {
                $select = array_diff($select, $builder->unselect);
            }
            if (empty($select)) $select = ['id'];
        }

        $formattedSelect = array_map(function($col) {
            $col = trim($col);

            // "table.column as alias" or "column as alias" or "column alias"
            if (preg_match('/^(.+?)\s+(?:as\s+)?(\w+)$/i', $col, $matches)) {
                return $this->getGrammar()->wrap($matches[1]) . " AS `{$matches[2]}`";
            }

            return $this->getGrammar()->wrap($col);
        }, $select);

        $sql = "SELECT " . implode(', ', $formattedSelect)
             . " FROM " . $this->getGrammar()->wrapTable($builder->table) . " ";
        $sql .= $this->compileJoins($builder);
        $params = [];
        $sql .= $this->compileWhere($builder, $params);

        if (!empty($builder->groupBy)) {
            $groups = array_map(function($g) {
                return $this->getGrammar()->wrap($g);
            }, $builder->groupBy);
            $sql .= " GROUP BY " . implode(', ', $groups);
        }

        if (!empty($builder->having)) {
            $sql .= $this->compileHaving($builder, $params);
        }

        if (!empty($builder->orderBy)) {
            $orders = array_map(function($o) {
                $direction = strtoupper($o['direction']) === 'DESC' ? 'DESC' : 'ASC';
                return $this->getGrammar()->wrap($o['column']) . " {$direction}";
            }, $builder->orderBy);
            $sql .= " ORDER BY " . implode(', ', $orders);
        }

        if ($builder->limit !== null) {
            $sql .= " LIMIT " . (int) $builder->limit;
            if ($builder->offset !== null) {
                $sql .= " OFFSET " . (int) $builder->offset;
            }
        }

        if ($builder->lockForUpdate) {
            $sql .= " FOR UPDATE";
        }

        return $this->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function insert(string $table, array $data)
    {
        $columns = $this->wrapColumnList(array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO " . $this->getGrammar()->wrapTable($table) . " ({$columns}) VALUES ({$placeholders})";
        $formattedValues = array_map([$this->getGrammar(), 'formatDate'], array_values($data));
        return $this->query($sql, $formattedValues);
    }

    public function update(string $table, array $data, array $where)
    {
        $set = [];
        $params = [];
        foreach ($data as $key => $value) {
            $set[] = '`' . $this->getGrammar()->validateIdentifier($key) . '` = ?';
            $params[] = $this->getGrammar()->formatDate($value);
        }
        $whereClause = [];
        foreach ($where as $key => $value) {
            $whereClause[] = '`' . $this->getGrammar()->validateIdentifier($key) . '` = ?';
            $params[] = $this->getGrammar()->formatDate($value);
        }
        $sql = "UPDATE " . $this->getGrammar()->wrapTable($table) . " SET " . implode(', ', $set)
             . " WHERE " . implode(' AND ', $whereClause);
        return $this->query($sql, $params);
    }

    public function delete(string $table, array $where)
    {
        $whereClause = [];
        $params = [];
        foreach ($where as $key => $value) {
            $whereClause[] = '`' . $this->getGrammar()->validateIdentifier($key) . '` = ?';
            $params[] = $value;
        }
        $sql = "DELETE FROM " . $this->getGrammar()->wrapTable($table) . " WHERE " . implode(' AND ', $whereClause);
        return $this->query($sql, $params);
    }

    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    public function createStorage(Schema $schema): void
    {
        $tableName = $this->getGrammar()->validateIdentifier($schema->table);
        $table = $this->getGrammar()->wrapTable($schema->table);
        $columnDefs = [];

        foreach ($schema->columns as $col) {
            $columnDefs[] = $this->buildColumnDefinition($col);
        }

        foreach ($schema->foreignKeys as $fk) {
            $col   = $this->getGrammar()->validateIdentifier($fk['column']);
            $refTb = $this->getGrammar()->validateIdentifier($fk['on']);
            $refCol = $this->getGrammar()->validateIdentifier($fk['references']);
            $onDel = $this->normalizeFkAction($fk['onDelete'] ?? 'RESTRICT');
            $onUpd = $this->normalizeFkAction($fk['onUpdate'] ?? 'CASCADE');
            $constraintName = "fk_{$tableName}_{$col}";
            $columnDefs[] = "CONSTRAINT `{$constraintName}` FOREIGN KEY (`{$col}`) REFERENCES `{$refTb}`(`{$refCol}`) ON DELETE {$onDel} ON UPDATE {$onUpd}";
        }

        if ($schema->primaryKey && strpos(strtoupper(implode('', $columnDefs)), 'PRIMARY KEY') === false) {
            $pk = $this->getGrammar()->validateIdentifier($schema->primaryKey);
            $columnDefs[] = "PRIMARY KEY (`{$pk}`)";
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (" . implode(', ', $columnDefs) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->query($sql);
        $this->createIndexes($schema);
    }

    public function alterStorage(Schema $schema): void
    {
        $table = $this->getGrammar()->wrapTable($schema->table);
        $clauses = [];

        foreach ($schema->droppedColumns as $column) {
            $col = $this->getGrammar()->validateIdentifier($column);
            $clauses[] = "DROP COLUMN `{$col}`";
        }

        foreach ($schema->columns as $column) {
            $definition = $this->buildColumnDefinition($column);
            $action = isset($column['modify']) && $column['modify'] ? 'MODIFY COLUMN' : 'ADD COLUMN';
            $after = '';
            if (isset($column['after'])) {
                $afterCol = $this->getGrammar()->validateIdentifier($column['after']);
                $after = " AFTER `{$afterCol}`";
            }
            $clauses[] = "{$action} {$definition}{$after}";
        }

        if (empty($clauses)) return;
        $sql = "ALTER TABLE {$table} " . implode(', ', $clauses);
        $this->query($sql);
    }

    private function normalizeFkAction(string $action): string
    {
        $action = strtoupper(trim($action));
        $allowed = ['CASCADE', 'RESTRICT', 'SET NULL', 'SET DEFAULT', 'NO ACTION'];
        return in_array($action, $allowed, true) ? $action : 'RESTRICT';
    }

    protected function buildColumnDefinition(array $col): string
    {
        $name = $this->getGrammar()->validateIdentifier($col['name']);
        $type = strtoupper($col['type']);

        switch ($type) {
            case 'ID':
                $sqlType = "BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
                break;
            case 'STRING':
                $sqlType = "VARCHAR(" . ($col['length'] ?? 255) . ")";
                break;
            case 'INTEGER':
                $sqlType = ($col['unsigned'] ?? false) ? "INT UNSIGNED" : "INT";
                break;
            case 'TINYINT':
                $sqlType = ($col['unsigned'] ?? false) ? "TINYINT UNSIGNED" : "TINYINT";
                break;
            case 'BIG_INTEGER':
                $sqlType = ($col['unsigned'] ?? false) ? "BIGINT UNSIGNED" : "BIGINT";
                break;
            case 'BOOLEAN':
                $sqlType = "TINYINT(1)";
                break;
            case 'DECIMAL':
                $sqlType = "DECIMAL({$col['precision']}, {$col['scale']})";
                break;
            case 'DATETIME':
                $sqlType = "DATETIME";
                break;
            case 'DATE':
                $sqlType = "DATE";
                break;
            case 'TIME':
                $sqlType = "TIME";
                break;
            case 'TIMESTAMP':
                $sqlType = "TIMESTAMP";
                break;
            case 'JSON':
                $sqlType = "JSON";
                break;
            case 'TEXT':
                $sqlType = "TEXT";
                break;
            case 'MEDIUMTEXT':
                $sqlType = "MEDIUMTEXT";
                break;
            case 'LONGTEXT':
                $sqlType = "LONGTEXT";
                break;
            case 'TINYTEXT':
                $sqlType = "TINYTEXT";
                break;
            case 'ENUM':
                $sqlType = "ENUM('" . implode("','", $col['allowed']) . "')";
                break;
            default:
                $sqlType = $type;
        }

        $definition = "`{$name}` {$sqlType}";
        $definition .= $col['nullable'] ? " NULL" : " NOT NULL";

        if ($col['default'] !== null) {
            if ($col['default'] === 'CURRENT_TIMESTAMP') {
                $definition .= " DEFAULT CURRENT_TIMESTAMP";
            } elseif ($col['default'] === 'CURRENT_TIMESTAMP_ON_UPDATE') {
                $definition .= " DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
            } else {
                $val = is_string($col['default']) ? "'{$col['default']}'" : $col['default'];
                if (is_bool($col['default'])) $val = $col['default'] ? '1' : '0';
                $definition .= " DEFAULT {$val}";
            }
        }

        if (isset($col['unique']) && $col['unique']) $definition .= " UNIQUE";
        if (isset($col['comment']) && !empty($col['comment'])) $definition .= " COMMENT '" . addslashes($col['comment']) . "'";

        return $definition;
    }

    private function createIndexes(Schema $schema): void
    {
        $table = $this->getGrammar()->validateIdentifier($schema->table);
        foreach (array_merge($schema->indexes, $schema->uniqueIndexes) as $index) {
            $type = (isset($index['unique']) || strpos($index['name'], 'uniq_') !== false) ? 'UNIQUE INDEX' : 'INDEX';
            $cols = implode(', ', array_map(function ($c) {
                return '`' . $this->getGrammar()->validateIdentifier($c) . '`';
            }, $index['columns']));
            $idxName = $this->getGrammar()->validateIdentifier($index['name']);
            $sql = "CREATE {$type} `{$idxName}` ON `{$table}` ({$cols})";
            try { $this->query($sql); } catch (\Exception $e) { /* already exists */ }
        }
    }

    public function dropStorage(string $name): void
    {
        $this->query("DROP TABLE IF EXISTS " . $this->getGrammar()->wrapTable($name));
    }

    /**
     * Check whether a column exists. Used by Schema::hasColumn().
     */
    public function hasColumn(string $table, string $column): bool
    {
        $table = $this->getGrammar()->validateIdentifier($table);
        $column = $this->getGrammar()->validateIdentifier($column);

        $stmt = $this->query(
            "SELECT 1 FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?
             LIMIT 1",
            [$table, $column]
        );
        return (bool) $stmt->fetchColumn();
    }

    public function ensureMigrationTracking(string $tableName): void
    {
        $tableName = $this->getGrammar()->validateIdentifier($tableName);
        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->query($sql);
    }

    public function beginTransaction(): void 
    { 
        if ($this->transactionDepth === 0) {
            $this->connection->beginTransaction(); 
        }
        $this->transactionDepth++;
    }

    public function commit(): void 
    { 
        $this->transactionDepth--;
        if ($this->transactionDepth === 0 && $this->connection->inTransaction()) {
            $this->connection->commit(); 
        }
    }

    public function rollBack(): void 
    { 
        if ($this->transactionDepth > 0) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack(); 
            }
            $this->transactionDepth = 0;
        }
    }
    public function inTransaction(): bool { return $this->connection->inTransaction(); }
}
