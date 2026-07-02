<?php

namespace Framework\Core\Database;

class QueryBuilder
{
    public $connection;
    public $table;
    public $select = ['*'];
    public $unselect = [];
    public $where = [];
    public $joins = [];
    public $orderBy = [];
    public $groupBy = [];
    public $having = [];
    public $limit = null;
    public $offset = null;
    public $operation = 'select';
    public $rawSql = '';
    public $rawParams = [];
    public $data = [];
    public $aggregateColumn = null;
    public $uniqueBy = [];
    public $lockForUpdate = false;

    /**
     * Set the database connection to use
     */
    public function on(?string $connection): self
    {
        $this->connection = $connection ?? DB::getDefaultConnectionName();
        return $this;
    }

    public function from(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Lock the selected rows in the database
     */
    public function lockForUpdate(): self
    {
        $this->lockForUpdate = true;
        return $this;
    }

    public function select(array $columns): self
    {
        $this->select = $columns;
        return $this;
    }

    public function unselect(array $columns): self
    {
        $this->unselect = $columns;
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = compact('table', 'first', 'operator', 'second', 'type');
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    public function where($column, $operator = null, $value = null, string $boolean = 'AND'): self
    {
        if ($column instanceof \Closure) {
            $this->where[] = [
                'type' => 'nested',
                'query' => $column,
                'boolean' => $boolean
            ];
            return $this;
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $this->where[] = compact('column', 'operator', 'value', 'boolean');
        return $this;
    }

    public function orWhere($column, $operator = null, $value = null): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND'): self
    {
        $this->where[] = [
            'column' => $column,
            'operator' => 'IN',
            'value' => $values,
            'boolean' => $boolean
        ];
        return $this;
    }

    public function whereRaw(string $sql, array $params = [], string $boolean = 'AND'): self
    {
        // Dev-mode foot-gun detector. Anything that looks like a variable
        // interpolation, superglobal reference, or request-sourced input
        // in a raw SQL fragment is almost always a bug. Only fires in
        // APP_DEBUG=true so production traffic isn't slowed.
        if (function_exists('config') && config('app.debug')) {
            $suspicious = ['$_GET', '$_POST', '$_REQUEST', '$_COOKIE',
                           'request->', 'input(', 'query(', 'post(', '{$'];
            foreach ($suspicious as $needle) {
                if (strpos($sql, $needle) !== false) {
                    trigger_error(
                        "whereRaw() contains a user-sourced substring ({$needle}). "
                        . "Use parameter bindings (the second arg) — the interpolated "
                        . "value is a SQL injection vector.",
                        E_USER_WARNING
                    );
                    break;
                }
            }
        }

        $this->where[] = [
            'type' => 'raw',
            'sql' => $sql,
            'params' => $params,
            'boolean' => $boolean
        ];
        return $this;
    }

    public function orWhereRaw(string $sql, array $params = []): self
    {
        return $this->whereRaw($sql, $params, 'OR');
    }

    /**
     * Append a raw expression to the SELECT list. Caller owns the SQL.
     * Wrap expression in parens already-quoted so Grammar::wrap() passes it through.
     */
    public function selectRaw(string $expression): self
    {
        $this->select[] = '(' . $expression . ')';
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = compact('column', 'direction');
        return $this;
    }

    /**
     * Order by a raw SQL fragment. Caller owns the SQL — typically used for
     * CASE WHEN, FIELD(), or aggregate-based orderings that strict identifier
     * validation correctly rejects.
     */
    public function orderByRaw(string $expression, string $direction = 'ASC'): self
    {
        // Pre-wrap with parens so the grammar's wrap() sees it as an expression
        // and returns it untouched.
        $this->orderBy[] = [
            'column' => '(' . $expression . ')',
            'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC',
        ];
        return $this;
    }

    public function groupBy(...$groups): self
    {
        foreach ($groups as $group) {
            $this->groupBy[] = $group;
        }
        return $this;
    }

    public function having($column, $operator = null, $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $this->having[] = compact('column', 'operator', 'value', 'boolean');
        return $this;
    }

    public function orHaving($column, $operator = null, $value = null): self
    {
        return $this->having($column, $operator, $value, 'OR');
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function raw(string $sql, array $params = []): self
    {
        $this->operation = 'raw';
        $this->rawSql = $sql;
        $this->rawParams = $params;
        return $this;
    }

    public function get(): array
    {
        if (empty($this->connection)) {
            $this->connection = DB::getDefaultConnectionName();
        }
        $result = DB::connection($this->connection)->executeBuilder($this);
        return is_array($result) ? $result : [];
    }

    public function execute()
    {
        if (empty($this->connection)) {
            $this->connection = DB::getDefaultConnectionName();
        }
        return DB::connection($this->connection)->executeBuilder($this);
    }

    public function first()
    {
        if (empty($this->connection)) {
            $this->connection = DB::getDefaultConnectionName();
        }
        $this->limit(1);
        $result = $this->get();
        return $result[0] ?? null;
    }

    public function count(): int
    {
        if (empty($this->connection)) {
            $this->connection = DB::getDefaultConnectionName();
        }
        $this->operation = 'count';
        return DB::connection($this->connection)->executeBuilder($this);
    }

    public function sum(string $column): float
    {
        if (empty($this->connection)) {
            $this->connection = DB::getDefaultConnectionName();
        }
        $this->operation = 'sum';
        $this->aggregateColumn = $column;
        return (float)DB::connection($this->connection)->executeBuilder($this);
    }

    public function avg(string $column): float
    {
        if (empty($this->connection)) {
            $this->connection = DB::getDefaultConnectionName();
        }
        $this->operation = 'avg';
        $this->aggregateColumn = $column;
        return (float)DB::connection($this->connection)->executeBuilder($this);
    }

    public function insert(array $data)
    {
        if (empty($this->connection)) {
            $this->connection = DB::getDefaultConnectionName();
        }
        $this->operation = 'insert';
        $this->data = $data;
        return DB::connection($this->connection)->executeBuilder($this);
    }

    public function update(array $data): int
    {
        if (empty($this->connection)) {
            $this->connection = DB::getDefaultConnectionName();
        }
        $this->operation = 'update';
        $this->data = $data;
        return (int)DB::connection($this->connection)->executeBuilder($this);
    }

    public function delete(): int
    {
        if (empty($this->connection)) {
            $this->connection = DB::getDefaultConnectionName();
        }
        $this->operation = 'delete';
        return (int)DB::connection($this->connection)->executeBuilder($this);
    }

    public function pluck(string $column, ?string $key = null): array
    {
        $this->select = $key ? [$key, $column] : [$column];
        $results = $this->get();
        
        $plucked = [];
        foreach ($results as $row) {
            $rowArr = is_object($row) ? (array) $row : $row;
            if ($key && isset($rowArr[$key])) {
                $plucked[$rowArr[$key]] = $rowArr[$column] ?? null;
            } else {
                $plucked[] = $rowArr[$column] ?? null;
            }
        }
        return $plucked;
    }

    public function value(string $column)
    {
        $result = $this->first();
        if (!$result) return null;
        $rowArr = is_object($result) ? (array) $result : $result;
        return $rowArr[$column] ?? null;
    }

    public function increment(string $column, float $amount = 1, array $extra = []): int
    {
        $this->operation = 'increment';
        $this->data = ['column' => $column, 'amount' => $amount, 'extra' => $extra];
        return (int)DB::connection($this->connection)->executeBuilder($this);
    }

    public function decrement(string $column, float $amount = 1, array $extra = []): int
    {
        $this->operation = 'decrement';
        $this->data = ['column' => $column, 'amount' => $amount, 'extra' => $extra];
        return (int)DB::connection($this->connection)->executeBuilder($this);
    }

    public function exists(): bool
    {
        if (empty($this->connection)) {
            $this->connection = DB::getDefaultConnectionName();
        }
        return $this->count() > 0;
    }
}
