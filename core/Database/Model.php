<?php

namespace Framework\Core\Database;

abstract class Model implements \JsonSerializable, \ArrayAccess
{
    protected $connection = 'default';
    protected $table = '';
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    protected $hidden = [];
    protected $attributes = [];
    protected $timestamps = true;
    protected $softDeletes = false;
    public $exists = false;
    
    protected $queryWhere = [];
    protected $querySelect = ['*'];
    protected $queryUnselect = [];
    protected $queryJoins = [];
    protected $relationUnselects = [];
    protected $queryOrderBy = [];
    protected $queryGroupBy = [];
    protected $queryHaving = [];
    protected $queryLimit = null;
    protected $queryOffset = null;
    protected $isChaining = false;
    
    protected $eagerLoad = [];
    protected $relations = [];
    protected $isLocked = false;
    
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Set the database connection for the model instance
     */
    public function setConnection(string $name): self
    {
        $this->connection = $name;
        return $this;
    }

    /**
     * Fluent method to switch connection (Internal)
     */
    protected function _on(string $connection): self
    {
        return $this->setConnection($connection);
    }

    // =========================================================
    // MAGIC METHODS (The Proxy Bridge)
    // =========================================================

    public static function __callStatic(string $method, array $parameters)
    {
        return (new static())->$method(...$parameters);
    }

    public function __call(string $method, array $parameters)
    {
        $internalMethod = '_' . $method;
        if (method_exists($this, $internalMethod)) {
            // Special handling for where methods to resolve 2 vs 3 arguments correctly
            if ($method === 'where' || $method === 'orWhere') {
                if (count($parameters) === 2) {
                    return $this->$internalMethod($parameters[0], '=', $parameters[1]);
                }
            }
            return $this->$internalMethod(...$parameters);
        }

        // Forward to QueryBuilder
        return $this->buildQuery()->$method(...$parameters);
    }

    // =========================================================
    // STRUCTURED QUERY METHODS (Internal Prefixed)
    // =========================================================

    protected function _where($column, $operator = null, $value = null, string $boolean = 'AND'): self
    {
        $this->isChaining = true;

        if ($column instanceof \Closure) {
            $nestedModel = new static();
            $nestedModel->isChaining = true;
            $column($nestedModel);
            
            $this->queryWhere[] = [
                'type' => 'nested',
                'query' => function($builder) use ($nestedModel) {
                    foreach ($nestedModel->queryWhere as $w) {
                        if (isset($w['type']) && $w['type'] === 'raw') {
                            $builder->whereRaw($w['sql'], $w['params'], $w['boolean']);
                        } elseif (isset($w['type']) && $w['type'] === 'nested') {
                             $builder->where($w['query'], null, null, $w['boolean']);
                        } else {
                            $builder->where($w['column'], $w['operator'], $w['value'], $w['boolean']);
                        }
                    }
                },
                'boolean' => $boolean
            ];
            return $this;
        }
        
        $this->queryWhere[] = [
            'column' => static::sanitizeColumn($column),
            'operator' => strtoupper($operator),
            'value' => $value,
            'boolean' => $boolean
        ];
        return $this;
    }

    protected function _whereIn(string $column, array $values): self
    {
        if (empty($values)) return $this;
        $this->isChaining = true;
        $this->queryWhere[] = [
            'column' => static::sanitizeColumn($column),
            'operator' => 'IN',
            'value' => $values,
            'boolean' => 'AND'
        ];
        return $this;
    }

    protected function _whereRaw(string $sql, array $params = [], string $boolean = 'AND'): self
    {
        $this->isChaining = true;
        $this->queryWhere[] = [
            'type' => 'raw',
            'sql' => $sql,
            'params' => $params,
            'boolean' => $boolean
        ];
        return $this;
    }

    protected function _whereDate(string $column, $value, string $operator = '='): self
    {
        $this->isChaining = true;
        $column = static::sanitizeColumn($column);
        if ($operator === '=' && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            return $this->_whereBetween($column, $value . ' 00:00:00', $value . ' 23:59:59');
        }
        $this->queryWhere[] = ['column' => $column, 'operator' => strtoupper($operator), 'value' => $value, 'boolean' => 'AND'];
        return $this;
    }

    protected function _whereMonth(string $column, $value, string $operator = '='): self
    {
        $this->isChaining = true;
        if ($operator === '=' && preg_match('/^\d{1,2}$/', trim($value))) {
            $year = date('Y');
            $month = str_pad($value, 2, '0', STR_PAD_LEFT);
            $start = "{$year}-{$month}-01 00:00:00";
            $end = date('Y-m-t 23:59:59', strtotime($start));
            return $this->_whereBetween($column, $start, $end);
        }
        $this->queryWhere[] = ['column' => static::sanitizeColumn($column), 'operator' => strtoupper($operator), 'value' => $value, 'boolean' => 'AND'];
        return $this;
    }

    protected function _whereYear(string $column, $value, string $operator = '='): self
    {
        $this->isChaining = true;
        if ($operator === '=' && preg_match('/^\d{4}$/', trim($value))) {
            return $this->_whereBetween($column, "{$value}-01-01 00:00:00", "{$value}-12-31 23:59:59");
        }
        $this->queryWhere[] = ['column' => static::sanitizeColumn($column), 'operator' => strtoupper($operator), 'value' => $value, 'boolean' => 'AND'];
        return $this;
    }

    protected function _whereBetween(string $column, $start, $end): self
    {
        $this->isChaining = true;
        $this->queryWhere[] = ['column' => static::sanitizeColumn($column), 'operator' => 'BETWEEN', 'value' => [$start, $end], 'boolean' => 'AND'];
        return $this;
    }

    protected function _orWhere($column, $operator = null, $value = null): self
    {
        return $this->_where($column, $operator, $value, 'OR');
    }

    protected function _orWhereRaw(string $sql, array $params = []): self
    {
        return $this->_whereRaw($sql, $params, 'OR');
    }

    protected function _whereNotIn(string $column, array $values): self
    {
        if (empty($values)) return $this;
        $this->isChaining = true;
        $this->queryWhere[] = [
            'column' => static::sanitizeColumn($column),
            'operator' => 'NOT IN',
            'value' => $values,
            'boolean' => 'AND'
        ];
        return $this;
    }

    protected function _whereNull(string $column): self
    {
        $this->isChaining = true;
        $this->queryWhere[] = [
            'column' => static::sanitizeColumn($column),
            'operator' => 'IS NULL',
            'value' => null,
            'boolean' => 'AND'
        ];
        return $this;
    }

    protected function _whereNotNull(string $column): self
    {
        $this->isChaining = true;
        $this->queryWhere[] = [
            'column' => static::sanitizeColumn($column),
            'operator' => 'IS NOT NULL',
            'value' => null,
            'boolean' => 'AND'
        ];
        return $this;
    }

    protected function _whereLike(string $column, string $value): self
    {
        $this->isChaining = true;
        $this->queryWhere[] = [
            'column' => static::sanitizeColumn($column),
            'operator' => 'LIKE',
            'value' => "%{$value}%",
            'boolean' => 'AND'
        ];
        return $this;
    }

    protected function _unselect($columns): self
    {
        $this->isChaining = true;
        if (is_string($columns)) {
            $columns = array_map('trim', explode(',', $columns));
        }
        
        foreach ($columns as $column) {
            if (strpos($column, '.') !== false || strpos($column, ':') !== false) {
                $delimiter = strpos($column, '.') !== false ? '.' : ':';
                [$relation, $relColumn] = explode($delimiter, $column, 2);
                
                if (!isset($this->relationUnselects[$relation])) {
                    $this->relationUnselects[$relation] = [];
                }
                $this->relationUnselects[$relation][] = trim($relColumn);
            } else {
                $this->queryUnselect[] = static::sanitizeColumn(trim($column));
            }
        }
        return $this;
    }
    protected function _orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->isChaining = true;
        $this->queryOrderBy[] = ['column' => static::sanitizeColumn($column), 'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC'];
        return $this;
    }

    protected function _limit(int $limit): self { $this->queryLimit = $limit; return $this; }

    protected function _groupBy(...$groups): self
    {
        foreach ($groups as $group) {
            $this->queryGroupBy[] = $group;
        }
        return $this;
    }

    protected function _having($column, $operator = null, $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $this->queryHaving[] = compact('column', 'operator', 'value', 'boolean');
        return $this;
    }

    protected function _orHaving($column, $operator = null, $value = null): self
    {
        return $this->_having($column, $operator, $value, 'OR');
    }

    protected function _offset(int $offset): self { $this->queryOffset = $offset; return $this; }

    protected function _select($columns): self
    {
        $this->isChaining = true;
        $columns = is_string($columns) ? array_map('trim', explode(',', $columns)) : $columns;
        $this->querySelect = array_map([static::class, 'sanitizeColumn'], $columns);
        return $this;
    }

    protected function _join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->isChaining = true;
        $this->queryJoins[] = compact('table', 'first', 'operator', 'second', 'type');
        return $this;
    }

    protected function _leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->_join($table, $first, $operator, $second, 'LEFT');
    }

    protected function _rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->_join($table, $first, $operator, $second, 'RIGHT');
    }

    public function save()
    {
        $now = date('Y-m-d H:i:s');
        
        if ($this->exists) {
            $id = $this->attributes[$this->primaryKey];
            $updateData = $this->attributes;
            unset($updateData[$this->primaryKey]);
            
            if ($this->timestamps && !isset($updateData['updated_at'])) {
                $updateData['updated_at'] = $now;
                $this->forceFill('updated_at', $now);
            }
            
            $builder = static::table($this->table)->on($this->connection)->where($this->primaryKey, '=', $id);
            $builder->operation = 'update';
            $builder->data = $updateData;
            return DB::connection($this->connection)->executeBuilder($builder);
        } else {
            if ($this->timestamps) {
                if (!isset($this->attributes['created_at'])) $this->forceFill('created_at', $now);
                if (!isset($this->attributes['updated_at'])) $this->forceFill('updated_at', $now);
            }
            
            $builder = static::table($this->table)->on($this->connection);
            $builder->operation = 'insert';
            $builder->data = $this->attributes;

            $id = DB::connection($this->connection)->executeBuilder($builder); 
            
            if ($id !== false) {
                if (!isset($this->attributes[$this->primaryKey])) {
                    $this->attributes[$this->primaryKey] = $id;
                }
                $this->exists = true;
                return $this->attributes[$this->primaryKey]; 
            }
            return false;
        }
    }

    public function delete()
    {
        if ($this->isChaining) {
            $builder = $this->buildQuery();
            $builder->operation = 'delete';
            return DB::connection($this->connection)->executeBuilder($builder);
        }

        if ($this->softDeletes) {
            $this->forceFill('deleted_at', date('Y-m-d H:i:s'));
            return $this->save();
        }
        return $this->forceDelete();
    }

    public function forceDelete()
    {
        $builder = static::table($this->table)->on($this->connection)->where($this->primaryKey, '=', $this->attributes[$this->primaryKey]);
        $builder->operation = 'delete';
        return DB::connection($this->connection)->executeBuilder($builder);
    }

    public function restore(): bool
    {
        if (!$this->softDeletes) return false;
        $this->forceFill('deleted_at', null);
        return (bool)$this->save();
    }

    protected function _count(): int { return $this->buildQuery()->count(); }
    protected function _exists($id = null): bool
    {
        if ($id !== null) {
            return $this->_where($this->primaryKey, $id)->buildQuery()->exists();
        }
        return $this->buildQuery()->exists();
    }
    protected function _sum(string $column): float { return $this->buildQuery()->sum($column); }
    

    protected function _first(): ?self 
    {  
        $results = $this->_limit(1)->get(); 
        return $results[0] ?? null; 
    }

    protected function _paginate(int $perPage = 15, int $page = 1): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        // Count total records without pagination constraints
        $total = $this->buildQuery()->count();

        // Apply pagination to the model state and fetch
        $this->queryLimit = $perPage;
        $this->queryOffset = $offset;
        
        $builder = $this->buildQuery();
        $models = [];
        foreach ($builder->get() as $row) {
            $attributes = is_object($row) ? (array) $row : $row;
            if (isset($attributes['_id']) && !isset($attributes[$this->primaryKey])) {
                $attributes[$this->primaryKey] = (string) $attributes['_id'];
            }
            $models[] = static::hydrate($attributes, $this->connection);
        }

        return [
            'data'       => $this->loadRelations($models),
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'total_pages'  => (int) ceil($total / $perPage),
                'has_next'     => $page < ceil($total / $perPage),
                'has_prev'     => $page > 1,
            ],
        ];
    }

    /**
     * Reload the model attributes from the database.
     */
    public function refresh(): self
    {
        if (!isset($this->attributes[$this->primaryKey])) {
            return $this;
        }

        $fresh = static::where($this->primaryKey, $this->attributes[$this->primaryKey])->first();
        if ($fresh) {
            $this->attributes = $fresh->attributes;
        }

        return $this;
    }

    protected function _lockForUpdate(): self
    {
        $this->isLocked = true;
        return $this;
    }

    public static function all(): array { return static::query()->get(); }
    public static function create(array $attributes): self { 
        $instance = new static($attributes); 
        $instance->save(); 
        return $instance; 
    }

    protected function _findOrFail($id)
    {
        $result = $this->_where($this->primaryKey, $id)->first();
        if (!$result) throw new \Exception("Model not found");
        return $result;
    }

    protected function _firstOrFail()
    {
        $result = $this->first();
        if (!$result) throw new \Exception("Model not found");
        return $result;
    }

    protected function _findMany(array $ids): array
    {
        if (empty($ids)) return [];
        return $this->_whereIn($this->primaryKey, $ids)->get();
    }

    protected function _latest(string $column = 'created_at'): self
    {
        return $this->_orderBy($column, 'DESC');
    }

    protected function _oldest(string $column = 'created_at'): self
    {
        return $this->_orderBy($column, 'ASC');
    }

    public static function firstOrCreate(array $attributes, array $values = []): self
    {
        $query = static::query();
        foreach ($attributes as $key => $value) {
            $query = $query->where($key, $value);
        }
        $instance = $query->first();
        if ($instance) return $instance;

        return static::create(array_merge($attributes, $values));
    }

    public static function updateOrCreate(array $attributes, array $values = []): self
    {
        $query = static::query();
        foreach ($attributes as $key => $value) {
            $query = $query->where($key, $value);
        }
        $instance = $query->first();
        
        if ($instance) {
            $instance->update($values);
            return $instance;
        }

        return static::create(array_merge($attributes, $values));
    }

    public static function createOrUpdate(array $attributes, array $values = []): self
    {
        return static::updateOrCreate($attributes, $values);
    }

    protected function _increment(string $column, float $amount = 1, array $extra = []): int
    {
        if ($this->exists) {
            $result = static::where($this->primaryKey, $this->id())->buildQuery()->increment($column, $amount, $extra);
            $this->attributes[$column] = ($this->attributes[$column] ?? 0) + $amount;
            foreach ($extra as $k => $v) {
                $this->attributes[$k] = $v;
            }
            return $result;
        }
        return $this->buildQuery()->increment($column, $amount, $extra);
    }

    protected function _decrement(string $column, float $amount = 1, array $extra = []): int
    {
        if ($this->exists) {
            $result = static::where($this->primaryKey, $this->id())->buildQuery()->decrement($column, $amount, $extra);
            $this->attributes[$column] = ($this->attributes[$column] ?? 0) - $amount;
            foreach ($extra as $k => $v) {
                $this->attributes[$k] = $v;
            }
            return $result;
        }
        return $this->buildQuery()->decrement($column, $amount, $extra);
    }

    protected function _chunk(int $count, callable $callback): bool
    {
        $page = 1;
        do {
            $results = $this->paginate($count, $page)['data'];
            $countResults = count($results);

            if ($countResults == 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
        } while ($countResults == $count);

        return true;
    }
    
    /**
     * Get the value of the primary key
     */
    public function id()
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    public function toJson(): string { return json_encode($this->toArray()); }
    
    public function __set(string $key, $value): void
    {
        if (in_array($key, $this->guarded)) return;
        
        // Allow setting any attribute, not just fillable ones
        $this->attributes[$key] = $value;
    }
    // =========================================================
    // RELATIONSHIPS
    // =========================================================

    protected function _with(string ...$relations): self
    {
        $this->eagerLoad = array_merge($this->eagerLoad, $relations);
        return $this;
    }

    public function hasOne(string $relatedClass, string $foreignKey, string $localKey = ''): ?self
    {
        $localKey = $localKey ?: $this->primaryKey;
        $localValue = $this->attributes[$localKey] ?? null;
        return $localValue ? $relatedClass::where($foreignKey, $localValue)->first() : null;
    }

    public function hasMany(string $relatedClass, string $foreignKey, string $localKey = ''): self
    {
        $localKey = $localKey ?: $this->primaryKey;
        $localValue = $this->attributes[$localKey] ?? null;
        return $relatedClass::where($foreignKey, $localValue);
    }

    public function belongsTo(string $relatedClass, string $foreignKey, string $ownerKey = ''): ?self
    {
        $foreignValue = $this->attributes[$foreignKey] ?? null;
        $ownerKey = $ownerKey ?: (new $relatedClass())->primaryKey;
        return $foreignValue ? $relatedClass::where($ownerKey, $foreignValue)->first() : null;
    }

    protected function loadRelations(array $models): array
    {
        if (empty($this->eagerLoad) || empty($models)) return $models;
        foreach ($this->eagerLoad as $relation) {
            $parsed = $this->parseRelationDefinition($relation);
            if ($parsed['localKey'] !== null) {
                $this->batchLoadRelation($models, $parsed);
            } elseif (method_exists($models[0], $parsed['name'])) {
                foreach ($models as $model) {
                    $result = $model->{$parsed['name']}();
                    // If it returns a chaining model (like from hasMany), execute get()
                    if ($result instanceof self && $result->isChaining) {
                        $model->relations[$parsed['name']] = $result->get();
                    } else {
                        $model->relations[$parsed['name']] = $result;
                    }
                }
            }
        }
        return $models;
    }

    /**
     * Eager load relations in a single batch query to avoid N+1 issues.
     * Fixed: Ensures keyValues is always an array to prevent MongoDB $in errors.
     */
    protected function batchLoadRelation(array &$models, array $parsed): void
    {
        $name       = $parsed['name'];
        $localKey   = $parsed['localKey'];
        $foreignKey = $parsed['foreignKey'];
        $type       = $parsed['type'];
        
        // 1. Resolve the related class (e.g., Category or ProductPricingTier)
        $relatedClass = $this->resolveRelatedClass($models[0], $name);
        $relatedInstance = new $relatedClass();
        
        // 2. Determine the lookup column (e.g., 'category_id' or '_id')
        $lookupKey = $foreignKey ?: $relatedInstance->primaryKey;
        
        // 3. Collect unique local keys from the parent models
        $keyValues = [];
        foreach ($models as $model) {
            // Using magic __get or attributes array
            $val = $model->$localKey ?? null;
            if ($val !== null && $val !== '') {
                $keyValues[] = $val;
            }
        }
        
        // CRITICAL FIX: Ensure clean, unique array for MongoDB $in operator
        $keyValues = array_values(array_unique($keyValues));
        
        // 4. Handle empty parent sets (e.g., no products have a category)
        if (empty($keyValues)) {
            foreach ($models as $model) {
                $model->relations[$name] = ($type === 'hasMany') ? [] : null;
            }
            return;
        }
        
        // 5. Build the query for the related items
        $query = $relatedClass::query()->whereIn($lookupKey, $keyValues);
        
        // Apply relation-specific unselects if defined (from unselect() method)
        if (!empty($this->relationUnselects[$name])) {
            $query->unselect($this->relationUnselects[$name]);
        }
        
        $relatedItems = $query->get();
        
        // 6. Index the results for O(1) matching
        $indexed = [];
        foreach ($relatedItems as $item) {
            $idxKey = (string) $item->$lookupKey;
            if ($type === 'hasMany') {
                $indexed[$idxKey][] = $item;
            } else {
                if (!isset($indexed[$idxKey])) {
                    $indexed[$idxKey] = $item;
                }
            }
        }
        
        // 7. Map the related items back to the parent models
        foreach ($models as $model) {
            $val = (string) ($model->$localKey ?? '');
            if ($type === 'hasMany') {
                $model->relations[$name] = $indexed[$val] ?? [];
            } else {
                $model->relations[$name] = $indexed[$val] ?? null;
            }
        }
    }

    /**
     * Parses the relation string. 
     * Format: "relationName:local_key,foreign_key,type"
     */
    protected function parseRelationDefinition(string $definition): array
    {
        if (strpos($definition, ':') === false) {
            return [
                'name' => $definition, 
                'localKey' => null, 
                'foreignKey' => '', 
                'type' => 'hasOne'
            ];
        }

        [$name, $spec] = explode(':', $definition, 2);
        $parts = explode(',', $spec);
        
        return [
            'name'       => $name,
            'localKey'   => $parts[0] ?? null,
            'foreignKey' => $parts[1] ?? '',
            'type'       => $parts[2] ?? 'hasOne',
        ];
    }

    /**
     * Dynamically finds the class for the relation.
     */
    protected function resolveRelatedClass(Model $model, string $name): string
    {
        $className = ucfirst($name);
        $namespace = substr(get_class($model), 0, strrpos(get_class($model), '\\'));
        
        $options = [
            $namespace . '\\' . $className,
            'App\\Models\\' . $className
        ];

        foreach ($options as $fqcn) {
            if (class_exists($fqcn)) return $fqcn;
        }
        
        throw new \RuntimeException("Cannot resolve related model class for '{$name}'");
    }

    // =========================================================
    // CORE EXECUTION & STATIC HELPERS
    // =========================================================

    public static function query(): self { $instance = new static(); $instance->isChaining = true; return $instance; }

    public static function find($id): ?self { return static::where((new static())->primaryKey, $id)->first(); }

    public static function findBy(string $column, $value): ?self { return static::where($column, $value)->first(); }

    protected function _get(): array
    {
        $builder = $this->buildQuery();

        $models = [];
        foreach ($builder->get() as $row) {
            $attributes = is_object($row) ? (array) $row : $row;
            if (isset($attributes['_id'])) $attributes[$this->primaryKey] = (string) $attributes['_id'];
            $models[] = static::hydrate($attributes, $this->connection);
        }
        return $this->loadRelations($models);
    }

    protected function buildQuery(): QueryBuilder
    {
        $builder = (new QueryBuilder())->on($this->connection)->from($this->table)->select($this->querySelect);
        
        if ($this->queryLimit) $builder->limit($this->queryLimit);
        if ($this->queryOffset) $builder->offset($this->queryOffset);

        foreach ($this->queryJoins as $j) {
            $builder->join($j['table'], $j['first'], $j['operator'], $j['second'], $j['type']);
        }
        foreach ($this->queryWhere as $w) {
            if (isset($w['type']) && $w['type'] === 'raw') {
                $builder->whereRaw($w['sql'], $w['params'], $w['boolean']);
            } elseif (isset($w['type']) && $w['type'] === 'nested') {
                $builder->where($w['query'], null, null, $w['boolean']);
            } else {
                $builder->where($w['column'], $w['operator'], $w['value'], $w['boolean']);
            }
        }
        foreach ($this->queryOrderBy as $o) $builder->orderBy($o['column'], $o['direction']);
        foreach ($this->queryGroupBy as $g) $builder->groupBy($g);
        foreach ($this->queryHaving as $h) $builder->having($h['column'], $h['operator'], $h['value'], $h['boolean']);
        
        if ($this->isLocked) {
            $builder->lockForUpdate();
        }

        return $builder;
    }

    protected static function hydrate($attributes, string $connection = 'default'): self
    {
        $instance = new static();
        $instance->setConnection($connection);
        foreach ((array)$attributes as $k => $v) $instance->forceFill($k, $v);
        $instance->exists = true;
        return $instance;
    }

    public function forceFill($key, $value = null): self 
    { 
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->attributes[$k] = $v;
            }
        } else {
            $this->attributes[$key] = $value; 
        }
        return $this; 
    }

    public function fill(array $attributes): self
    {
        foreach ($attributes as $k => $v) {
            if (!in_array($k, $this->guarded) && (empty($this->fillable) || in_array($k, $this->fillable))) $this->attributes[$k] = $v;
        }
        return $this;
    }

    public function update(array $attributes)
    {
        $this->fill($attributes);
        return $this->save();
    }

    public static function table(string $table): QueryBuilder { return (new QueryBuilder())->from($table); }

    protected static function sanitizeColumn(string $column): string
    {
        if (!preg_match('/^[a-zA-Z0-9_\.\* \(\),!\'<>=]+$/i', $column)) throw new \InvalidArgumentException("Invalid column: {$column}");
        return $column;
    }

    public function toArray(): array
    {
        $array = $this->attributes;
        foreach ($this->hidden as $k) unset($array[$k]);
        foreach ($this->relations as $n => $v) {
            if (is_array($v)) $array[$n] = array_map(function($i) { return $i instanceof Model ? $i->toArray() : $i; }, $v);
            elseif ($v instanceof self) $array[$n] = $v->toArray();
            else $array[$n] = $v;
        }
        return $array;
    }

     public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __get(string $k)
    {
        if (array_key_exists($k, $this->attributes)) {
            return $this->attributes[$k];
        }

        if (array_key_exists($k, $this->relations)) {
            return $this->relations[$k];
        }

        // Handle dynamic relationship loading
        if (method_exists($this, $k)) {
            $relation = $this->$k();
            if ($relation instanceof self) {
                // If it's a relationship query builder, fetch results
                // We don't know for sure if it's hasOne or hasMany here 
                // without more metadata, but usually if we access as property
                // and it was intended to be a relation, we can try to guess or just get all.
                // However, batchLoadRelation handles hasMany vs hasOne.
                
                // For now, let's just execute the query. 
                // If it's used in a foreach, it needs to be an array.
                $results = $relation->get();
                $this->relations[$k] = $results;
                return $results;
            }
            return $relation;
        }

        return null;
    }

    public function __isset(string $k): bool
    {
        return isset($this->attributes[$k]);
    }

    public function __unset(string $k): void
    {
        unset($this->attributes[$k]);
    }

    // =========================================================
    // ARRAY ACCESS IMPLEMENTATION
    // =========================================================

    public function offsetExists($offset): bool { return isset($this->attributes[$offset]); }
    public function offsetGet($offset) { return $this->attributes[$offset] ?? null; }
    public function offsetSet($offset, $value): void { $this->attributes[$offset] = $value; }
    public function offsetUnset($offset): void { unset($this->attributes[$offset]); }

}
