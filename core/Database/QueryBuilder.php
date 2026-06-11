<?php

namespace Framework\Core\Database;

class QueryBuilder
{
    public $connection = 'default';
    public $table;
    public $select = ['*'];
    public $unselect = [];
    public $where = [];
    public $joins = [];
    public $orderBy = [];
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
        $this->connection = $connection ?? 'default';
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

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = compact('column', 'direction');
        return $this;
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
        $result = DB::connection($this->connection)->executeBuilder($this);
        return is_array($result) ? $result : [];
    }

    public function execute()
    {
        return DB::connection($this->connection)->executeBuilder($this);
    }

    public function first()
    {
        $this->limit(1);
        $result = $this->get();
        return $result[0] ?? null;
    }

    public function count(): int
    {
        $this->operation = 'count';
        return DB::connection($this->connection)->executeBuilder($this);
    }

    public function sum(string $column): float
    {
        $this->operation = 'sum';
        $this->aggregateColumn = $column;
        return (float)DB::connection($this->connection)->executeBuilder($this);
    }

    public function avg(string $column): float
    {
        $this->operation = 'avg';
        $this->aggregateColumn = $column;
        return (float)DB::connection($this->connection)->executeBuilder($this);
    }

    public function insert(array $data)
    {
        $this->operation = 'insert';
        $this->data = $data;
        return DB::connection($this->connection)->executeBuilder($this);
    }

    public function update(array $data): int
    {
        $this->operation = 'update';
        $this->data = $data;
        return (int)DB::connection($this->connection)->executeBuilder($this);
    }

    public function delete(): int
    {
        $this->operation = 'delete';
        return (int)DB::connection($this->connection)->executeBuilder($this);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }
}
