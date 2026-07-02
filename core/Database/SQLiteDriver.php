<?php

namespace Framework\Core\Database;

use PDO;
use PDOException;

class SQLiteDriver implements DatabaseDriverInterface
{
    protected $config = [];
    protected $connection = null;
    protected $grammar = null;
    protected $transactionDepth = 0;

    public function getGrammar(): Grammar
    {
        if (!$this->grammar) {
            $this->grammar = new SQLiteGrammar();
        }
        return $this->grammar;
    }

    public function connect(array $config): void
    {
        $this->config = $config;
        $dsn = "sqlite:{$config['database']}";
        try {
            $this->connection = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->connection->exec("PRAGMA foreign_keys = ON;");
        } catch (PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public function disconnect(): void
    {
        $this->connection = null;
        $this->transactionDepth = 0;
    }

    private function isConnectionLost(PDOException $e): bool
    {
        $code = $e->getCode();
        $message = $e->getMessage();
        return in_array($code, ['HY000']) || strpos($message, 'database is locked') !== false;
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
            case 'select':    return $this->handleSelect($builder);
            case 'insert':    return $this->handleInsert($builder);
            case 'update':    return $this->handleUpdate($builder);
            case 'upsert':    return $this->handleUpsert($builder);
            case 'delete':    return $this->handleDelete($builder);
            case 'count':     return $this->handleCount($builder);
            case 'sum':       return $this->handleSum($builder);
            case 'avg':       return $this->handleAvg($builder);
            case 'raw':       return $this->handleRaw($builder);
            case 'increment': return $this->handleIncrement($builder, '+');
            case 'decrement': return $this->handleIncrement($builder, '-');
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
        if (preg_match('/^\s*\(?\s*(SELECT|EXPLAIN|WITH|VALUES|PRAGMA)\b/i', $builder->rawSql)) {
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        }
        return $statement->rowCount();
    }

    private function handleInsert(QueryBuilder $builder)
    {
        $table = $this->getGrammar()->wrapTable($builder->table);
        $data = $builder->data;

        if (empty($data)) return false;

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

    private function handleUpsert(QueryBuilder $builder)
    {
        $table = $this->getGrammar()->wrapTable($builder->table);
        $data = $builder->data;
        $uniqueBy = $builder->uniqueBy;

        if (empty($data) || empty($uniqueBy)) return false;

        $isBatch = isset($data[0]) && is_array($data[0]);
        $rows = $isBatch ? $data : [$data];

        $columns = array_keys($rows[0]);
        $columnList = $this->wrapColumnList($columns);
        $conflict = $this->wrapColumnList($uniqueBy);

        $updateParts = [];
        foreach ($columns as $col) {
            if (!in_array($col, $uniqueBy, true)) {
                $wrapped = $this->getGrammar()->wrap($col);
                $updateParts[] = "{$wrapped} = excluded.{$wrapped}";
            }
        }

        $values = [];
        $params = [];
        foreach ($rows as $row) {
            $placeholders = implode(', ', array_fill(0, count($row), '?'));
            $values[] = "({$placeholders})";
            foreach ($columns as $col) {
                $params[] = $row[$col] ?? null;
            }
        }

        $sql = "INSERT INTO {$table} ({$columnList}) VALUES " . implode(', ', $values)
             . " ON CONFLICT ({$conflict}) ";
        $sql .= empty($updateParts) ? "DO NOTHING" : "DO UPDATE SET " . implode(', ', $updateParts);

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
            $sets[] = $this->getGrammar()->wrap($col) . " = ?";
            $params[] = $val;
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $sets);
        $sql .= $this->compileWhere($builder, $params);

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    private function handleIncrement(QueryBuilder $builder, string $operator = '+')
    {
        $table = $this->getGrammar()->wrapTable($builder->table);
        $column = $this->getGrammar()->wrap($builder->data['column']);
        $amount = (float) $builder->data['amount'];
        $extra = $builder->data['extra'] ?? [];
        $params = [];

        $sets = ["{$column} = {$column} {$operator} {$amount}"];

        foreach ($extra as $col => $val) {
            $sets[] = $this->getGrammar()->wrap($col) . " = ?";
            $params[] = $this->getGrammar()->formatDate($val);
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $sets);
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
                    $nestedSql = substr($nestedSql, 7);
                    $clauses[] = "{$boolean}({$nestedSql})";
                }
                continue;
            }

            $operator = strtoupper($w['operator']);
            $column = $this->getGrammar()->wrap($w['column']);

            if ($operator === 'IN' || $operator === 'NOT IN') {
                if (empty($w['value'])) {
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
                $clauses[] = "{$boolean}strftime('%Y', {$column}) = ?";
                $params[] = str_pad((string) $w['value'], 4, '0', STR_PAD_LEFT);
            } elseif ($operator === 'MONTH') {
                $clauses[] = "{$boolean}strftime('%m', {$column}) = ?";
                $params[] = str_pad((string) $w['value'], 2, '0', STR_PAD_LEFT);
            } elseif ($operator === 'DATE') {
                $clauses[] = "{$boolean}date({$column}) = ?";
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
                // pragma_table_info accepts a bound parameter — keep it parameterized.
                $stmt = $this->query(
                    "SELECT name FROM pragma_table_info(?)",
                    [$this->getGrammar()->validateIdentifier($builder->table)]
                );
                $allColumns = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                $select = array_diff($allColumns, $builder->unselect);
            } else {
                $select = array_diff($select, $builder->unselect);
            }
            if (empty($select)) $select = ['id'];
        }

        $formattedSelect = array_map(function($col) {
            $col = trim($col);
            if (preg_match('/^(.+?)\s+(?:as\s+)?(\w+)$/i', $col, $matches)) {
                return $this->getGrammar()->wrap($matches[1]) . " AS \"{$matches[2]}\"";
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

        // SQLite has no row-level locks within a single connection. lockForUpdate
        // is a no-op here; the immediate-transaction setting on the connection
        // covers the same need.

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
            $set[] = $this->getGrammar()->wrap($key) . " = ?";
            $params[] = $this->getGrammar()->formatDate($value);
        }
        $whereClause = [];
        foreach ($where as $key => $value) {
            $whereClause[] = $this->getGrammar()->wrap($key) . " = ?";
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
            $whereClause[] = $this->getGrammar()->wrap($key) . " = ?";
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
            $columnDefs[] = "FOREIGN KEY (\"{$col}\") REFERENCES \"{$refTb}\"(\"{$refCol}\") ON DELETE {$onDel} ON UPDATE {$onUpd}";
        }

        if ($schema->primaryKey && strpos(strtoupper(implode('', $columnDefs)), 'PRIMARY KEY') === false) {
            $pk = $this->getGrammar()->validateIdentifier($schema->primaryKey);
            $columnDefs[] = "PRIMARY KEY (\"{$pk}\")";
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (" . implode(', ', $columnDefs) . ")";
        $this->query($sql);
        $this->createIndexes($schema);
    }

    public function alterStorage(Schema $schema): void
    {
        $table = $this->getGrammar()->wrapTable($schema->table);

        // SQLite only supports ADD COLUMN and DROP COLUMN (3.35+).
        // MODIFY COLUMN requires a table rebuild, which we don't attempt here.
        foreach ($schema->droppedColumns as $column) {
            $col = $this->getGrammar()->validateIdentifier($column);
            $this->query("ALTER TABLE {$table} DROP COLUMN \"{$col}\"");
        }

        foreach ($schema->columns as $column) {
            if (isset($column['modify']) && $column['modify']) {
                throw new \BadMethodCallException(
                    "SQLiteDriver: MODIFY COLUMN is not supported. Recreate the table to change a column type."
                );
            }
            $definition = $this->buildColumnDefinition($column);
            $this->query("ALTER TABLE {$table} ADD COLUMN {$definition}");
        }
    }

    protected function buildColumnDefinition(array $col): string
    {
        $name = $this->getGrammar()->validateIdentifier($col['name']);
        $sqlType = $this->sqliteType($col);

        $definition = "\"{$name}\" {$sqlType}";
        $definition .= ($col['nullable'] ?? false) ? " NULL" : " NOT NULL";

        if (($col['default'] ?? null) !== null) {
            $default = $col['default'];
            if ($default === 'CURRENT_TIMESTAMP' || $default === 'CURRENT_TIMESTAMP_ON_UPDATE') {
                $definition .= " DEFAULT CURRENT_TIMESTAMP";
            } elseif (is_bool($default)) {
                $definition .= " DEFAULT " . ($default ? '1' : '0');
            } elseif (is_numeric($default)) {
                $definition .= " DEFAULT {$default}";
            } else {
                $escaped = str_replace("'", "''", (string) $default);
                $definition .= " DEFAULT '{$escaped}'";
            }
        }

        $type = strtoupper($col['type']);
        if ($type === 'ENUM' && !empty($col['allowed'])) {
            $list = implode(',', array_map(function ($v) {
                return "'" . str_replace("'", "''", $v) . "'";
            }, $col['allowed']));
            $definition .= " CHECK (\"{$name}\" IN ({$list}))";
        }

        if (!empty($col['unsigned']) && in_array($type, ['INTEGER', 'TINYINT', 'BIG_INTEGER'], true)) {
            $definition .= " CHECK (\"{$name}\" >= 0)";
        }

        if (!empty($col['unique'])) {
            $definition .= " UNIQUE";
        }

        // SQLite has no per-column COMMENT clause; we silently drop comments.

        return $definition;
    }

    /**
     * Map the framework's polyglot types onto SQLite's type-affinity system.
     */
    protected function sqliteType(array $col): string
    {
        $type = strtoupper($col['type']);

        switch ($type) {
            case 'ID':           return 'INTEGER PRIMARY KEY AUTOINCREMENT';
            case 'STRING':       return 'VARCHAR(' . (int) ($col['length'] ?? 255) . ')';
            case 'INTEGER':
            case 'TINYINT':
            case 'BIG_INTEGER':  return 'INTEGER';
            case 'BOOLEAN':      return 'INTEGER';
            case 'DECIMAL':
                $p = (int) ($col['precision'] ?? 10);
                $s = (int) ($col['scale'] ?? 2);
                return "NUMERIC({$p}, {$s})";
            case 'DATETIME':
            case 'TIMESTAMP':    return 'DATETIME';
            case 'DATE':         return 'DATE';
            case 'TIME':         return 'TIME';
            case 'JSON':         return 'TEXT';
            case 'TEXT':
            case 'MEDIUMTEXT':
            case 'LONGTEXT':
            case 'TINYTEXT':     return 'TEXT';
            case 'ENUM':         return 'TEXT';
            default:             return $type;
        }
    }

    private function normalizeFkAction(string $action): string
    {
        $action = strtoupper(trim($action));
        $allowed = ['CASCADE', 'RESTRICT', 'SET NULL', 'SET DEFAULT', 'NO ACTION'];
        return in_array($action, $allowed, true) ? $action : 'RESTRICT';
    }

    private function createIndexes(Schema $schema): void
    {
        $tableName = $this->getGrammar()->validateIdentifier($schema->table);
        foreach (array_merge($schema->indexes, $schema->uniqueIndexes) as $index) {
            $unique = !empty($index['unique']) || strpos($index['name'], 'uniq_') !== false;
            $type = $unique ? 'UNIQUE INDEX' : 'INDEX';
            $cols = array_map(function ($c) {
                return '"' . $this->getGrammar()->validateIdentifier($c) . '"';
            }, $index['columns']);
            $idxName = $this->getGrammar()->validateIdentifier($index['name']);
            $sql = "CREATE {$type} IF NOT EXISTS \"{$idxName}\" ON \"{$tableName}\" (" . implode(', ', $cols) . ")";
            try { $this->query($sql); } catch (\Exception $e) { /* ignore */ }
        }
    }

    public function dropStorage(string $name): void
    {
        $this->query("DROP TABLE IF EXISTS " . $this->getGrammar()->wrapTable($name));
    }

    /**
     * Check whether a column exists. Used by Schema::hasColumn().
     * pragma_table_info reflects the actual catalog (not the parser's leniency
     * about quoted unknown identifiers), so this returns the truth.
     */
    public function hasColumn(string $table, string $column): bool
    {
        $table = $this->getGrammar()->validateIdentifier($table);
        $column = $this->getGrammar()->validateIdentifier($column);

        $stmt = $this->query(
            "SELECT 1 FROM pragma_table_info(?) WHERE name = ? LIMIT 1",
            [$table, $column]
        );
        return (bool) $stmt->fetchColumn();
    }

    public function ensureMigrationTracking(string $tableName): void
    {
        $tableName = $this->getGrammar()->validateIdentifier($tableName);
        $sql = "CREATE TABLE IF NOT EXISTS \"{$tableName}\" (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration VARCHAR(255) NOT NULL,
            batch INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $this->query($sql);
    }

    private function wrapColumnList(array $columns): string
    {
        return implode(', ', array_map(function ($c) {
            return '"' . $this->getGrammar()->validateIdentifier($c) . '"';
        }, $columns));
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

    public function inTransaction(): bool
    {
        return $this->connection !== null && $this->connection->inTransaction();
    }
}
