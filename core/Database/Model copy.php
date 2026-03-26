<?php

namespace Framework\Core\Database;

abstract class Model2
{
    protected $db;
    protected $table = '';
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    protected $hidden = [];
    protected $attributes = [];
    protected $timestamps = true;
    protected $softDeletes = false;
    
    // Structured Query Builder properties
    protected $queryWhere = [];
    protected $querySelect = ['*'];
    protected $queryUnselect = [];
    protected $relationUnselects = [];
    protected $queryOrderBy = [];
    protected $queryLimit = null;
    protected $queryOffset = null;
    protected $isChaining = false;
    
    protected $eagerLoad = [];
    protected $relations = [];
    
    public function __construct(array $attributes = [])
    {
        $this->db = DB::connection();
        $this->fill($attributes);
    }
    
    public static function __callStatic(string $method, array $parameters)
    {
        return (new static())->$method(...$parameters);
    }
    
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->guarded)) continue;
            if (!empty($this->fillable) && in_array($key, $this->fillable)) {
                $this->attributes[$key] = $value;
            }
        }
        return $this;
    }
    
    protected function forceFill(string $key, $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }
    
    protected static function hydrate($attributes): self
    {
        $attributes = (array) $attributes; // Force cast to array internally
        $instance = new static();
        foreach ($attributes as $key => $value) {
            $instance->forceFill($key, $value);
        }
        return $instance;
    }
    
    public function save()
    {
        $now = date('Y-m-d H:i:s');
        
        if (isset($this->attributes[$this->primaryKey])) {
            $id = $this->attributes[$this->primaryKey];
            $updateData = $this->attributes;
            unset($updateData[$this->primaryKey]);
            
            if ($this->timestamps && !isset($updateData['updated_at'])) {
                $updateData['updated_at'] = $now;
                $this->forceFill('updated_at', $now);
            }
            
            $builder = self::table($this->table)->where($this->primaryKey, '=', $id);
            $builder->operation = 'update';
            $builder->data = $updateData;
            return DB::connection()->executeBuilder($builder);
        } else {
            if ($this->timestamps) {
                if (!isset($this->attributes['created_at'])) $this->forceFill('created_at', $now);
                if (!isset($this->attributes['updated_at'])) $this->forceFill('updated_at', $now);
            }
            
            $builder = self::table($this->table);
            $builder->operation = 'insert';
            $builder->data = $this->attributes;

            // Ensure this returns the ID, not just 'true'
            $id = DB::connection()->executeBuilder($builder); 
            
            if ($id) {
                $this->forceFill($this->primaryKey, $id);
                // Return the ID or the instance
                return $id; 
            }
            
            return false;
        }
    }
    
    protected static function sanitizeColumn(string $column): string
    {
        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }
        return $column;
    }

    // =========================================================
    // POLYGLOT QUERY BUILDER GENERATION
    // =========================================================

    protected function buildQuery(): QueryBuilder
    {
        $builder = self::table($this->table)->select($this->querySelect);
        
        if (!empty($this->queryUnselect)) {
            $builder->unselect($this->queryUnselect);
        }

        // Pass structured data safely to the builder (No SQL strings!)
        foreach ($this->queryWhere as $w) {
            $builder->where($w['column'], $w['operator'], $w['value'], $w['boolean']);
        }

        foreach ($this->queryOrderBy as $order) {
            $builder->orderBy($order['column'], $order['direction']);
        }

        return $builder;
    }
    
    // =========================================================
    // STRUCTURED WHERE METHODS
    // =========================================================

    public function where(string $column, $value, string $operator = '='): self
    {
        $this->isChaining = true;
        $this->queryWhere[] = [
            'column' => static::sanitizeColumn($column),
            'operator' => strtoupper($operator),
            'value' => $value,
            'boolean' => 'AND'
        ];
        return $this;
    }
    
    public function orWhere(string $column, $value, string $operator = '='): self
    {
        $this->isChaining = true;
        if (empty($this->queryWhere)) return $this->where($column, $value, $operator);
        
        $this->queryWhere[] = [
            'column' => static::sanitizeColumn($column),
            'operator' => strtoupper($operator),
            'value' => $value,
            'boolean' => 'OR'
        ];
        return $this;
    }

    public function whereIn(string $column, array $values): self
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

    public function whereNotIn(string $column, array $values): self
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
    
    public function whereNull(string $column): self
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
    
    public function whereNotNull(string $column): self
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
    
    public function whereLike(string $column, string $value): self
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

    public function whereBetween(string $column, $start, $end): self
    {
        $this->isChaining = true;
        $this->queryWhere[] = [
            'column' => static::sanitizeColumn($column),
            'operator' => 'BETWEEN',
            'value' => [$start, $end],
            'boolean' => 'AND'
        ];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->isChaining = true;
        $this->queryOrderBy[] = [
            'column' => static::sanitizeColumn($column),
            'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC'
        ];
        return $this;
    }
    
    // =========================================================
    // EXECUTION & PAGINATION
    // =========================================================


    public function get(): array
    {
        $builder = $this->buildQuery();
        if ($this->queryLimit !== null) {
            $builder->limit($this->queryLimit);
            if ($this->queryOffset !== null) $builder->offset($this->queryOffset);
        }

        $models = [];
        foreach ($builder->get() as $row) {
            // POLYGLOT FIX: Convert stdClass (MongoDB) to array if necessary
            $attributes = is_object($row) ? (array) $row : $row;
            
            // Ensure MongoDB _id is mapped to the model primary key 'id'
            if (isset($attributes['_id']) && !isset($attributes[$this->primaryKey])) {
                $attributes[$this->primaryKey] = (string) $attributes['_id'];
            }

            $models[] = static::hydrate($attributes);
        }
        return $this->loadRelations($models);
    }
    
    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $builder = $this->buildQuery();
        $total = $builder->count();
        
        $builder->limit($perPage)->offset($offset);
        $models = [];
        foreach ($builder->get() as $row) {
            // MISSING POLYGLOT CHECK: Add the same logic from get() here
            $attributes = is_object($row) ? (array) $row : $row;
            
            if (isset($attributes['_id']) && !isset($attributes[$this->primaryKey])) {
                $attributes[$this->primaryKey] = (string) $attributes['_id'];
            }

            $models[] = static::hydrate($attributes);
        }

        return [
            'data' => $this->loadRelations($models),
            'pagination' => [
                'current_page' => $page, 'per_page' => $perPage,
                'total' => $total, 'total_pages' => (int)ceil($total / $perPage),
                'has_next' => $page < ceil($total / $perPage), 'has_prev' => $page > 1,
            ],
        ];
    }
    
    public function limit(int $limit): self
    {
        $this->queryLimit = $limit;
        return $this;
    }
    
    public function offset(int $offset): self
    {
        $this->queryOffset = $offset;
        return $this;
    }
    public function count(): int { 
        return $this->buildQuery()->count(); 
    }
    public function exists(): bool { 
        return $this->buildQuery()->exists(); 
    }
    public function first(): ?self {  
        $results = $this->limit(1)->get(); 
        return $results[0] ?? null; 
    }

    public function sum(string $column): float { 
        return $this->buildQuery()->sum($column); 
    }
    
    // =========================================================
    // STATIC HELPERS
    // =========================================================

    public static function query(): self
    {
        $instance = new static();
        $instance->isChaining = true;
        if ($instance->softDeletes) $instance->whereNull('deleted_at'); // Polyglot soft delete
        return $instance;
    }

    public static function find($id): ?self { return static::query()->where((new static())->primaryKey, $id)->first(); }
    public static function findBy(string $column, $value): ?self { return static::query()->where($column, $value)->first(); }
    public static function search(string $column, string $value): array { return static::query()->whereLike($column, $value)->get(); }
    public static function all(): array { return static::query()->get(); }
    
    public static function table(string $table): QueryBuilder { return (new QueryBuilder())->from($table); }
    public static function create(array $attributes): self { $instance = new static($attributes); $instance->save(); return $instance; }
    
    public function delete()
    {
        if ($this->softDeletes) {
            $this->forceFill('deleted_at', date('Y-m-d H:i:s'));
            return $this->save();
        }
        return $this->forceDelete();
    }
    
    public function forceDelete()
    {
        $builder = self::table($this->table)->where($this->primaryKey, '=', $this->attributes[$this->primaryKey]);
        $builder->operation = 'delete';
        return DB::connection()->executeBuilder($builder);
    }
    
    // =========================================================
    // SELECT & UNSELECT METHODS
    // =========================================================

    public function select($columns): self
    {
        $this->isChaining = true;
        if (is_string($columns)) {
            $columns = array_map('trim', explode(',', $columns));
        }
        $this->querySelect = array_map([static::class, 'sanitizeColumn'], $columns);
        return $this;
    }

    public function unselect($columns): self
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


    // =========================================================
    // RELATIONSHIPS
    // =========================================================

    public function with(string ...$relations): self
    {
        $this->eagerLoad = array_merge($this->eagerLoad, $relations);
        return $this;
    }
    
    public function hasOne(string $relatedClass, string $foreignKey, string $localKey = ''): ?self
    {
        $localKey = $localKey ?: $this->primaryKey;
        $localValue = $this->$localKey ?? null;
        if ($localValue === null) return null;
        return $relatedClass::query()->where($foreignKey, $localValue)->first();
    }
    
    // public function hasMany(string $relatedClass, string $foreignKey, string $localKey = ''): array
    // {
    //     $localKey = $localKey ?: $this->primaryKey;
    //     $localValue = $this->$localKey ?? null;
    //     if ($localValue === null) return [];
    //     return $relatedClass::query()->where($foreignKey, $localValue)->get();
    // }
    
    // public function belongsTo(string $relatedClass, string $foreignKey, string $ownerKey = ''): ?self
    // {
    //     $foreignValue = $this->$foreignKey ?? null;
    //     if ($foreignValue === null) return null;
    //     if ($ownerKey) return $relatedClass::query()->where($ownerKey, $foreignValue)->first();
    //     return $relatedClass::find($foreignValue);
    // }

    public function hasMany(string $relatedClass, string $foreignKey, string $localKey = ''): self
    {
        $localKey = $localKey ?: $this->primaryKey;
        $localValue = $this->attributes[$localKey] ?? null;
        
        // Return a chainable model instance pre-filtered by the foreign key
        return $relatedClass::query()->where($foreignKey, $localValue);
    }

    public function belongsTo(string $relatedClass, string $foreignKey, string $ownerKey = ''): ?self
    {
        $foreignValue = $this->attributes[$foreignKey] ?? null;
        $ownerKey = $ownerKey ?: (new $relatedClass())->primaryKey;
        
        return $relatedClass::query()->where($ownerKey, $foreignValue)->first();
    }
    
    protected function loadRelations(array $models): array
    {
        if (empty($this->eagerLoad) || empty($models)) return $models;
        
        foreach ($this->eagerLoad as $relation) {
            $parsed = $this->parseRelationDefinition($relation);
            $name = $parsed['name'];
            
            if ($parsed['localKey'] !== null) {
                $this->batchLoadRelation($models, $parsed);
            } elseif (method_exists($models[0], $name)) {
                foreach ($models as $model) {
                    $model->relations[$name] = $model->$name();
                }
            }
        }
        return $models;
    }
    
    protected function batchLoadRelation(array &$models, array $parsed): void
    {
        $name      = $parsed['name'];
        $localKey  = $parsed['localKey'];
        $foreignKey = $parsed['foreignKey'];
        $type      = $parsed['type'];
        
        $relatedClass = $this->resolveRelatedClass($models[0], $name);
        $relatedInstance = new $relatedClass();
        $lookupKey = $foreignKey ?: $relatedInstance->primaryKey;
        
        $keyValues = [];
        foreach ($models as $model) {
            $val = $model->$localKey ?? null;
            if ($val !== null && $val !== '') {
                $keyValues[] = $val;
            }
        }
        $keyValues = array_values(array_unique($keyValues));
        
        if (empty($keyValues)) {
            foreach ($models as $model) {
                $model->relations[$name] = ($type === 'hasMany') ? [] : null;
            }
            return;
        }
        
        $query = $relatedClass::query()->whereIn($lookupKey, $keyValues);
        
        if (!empty($this->relationUnselects[$name])) {
            $query->unselect($this->relationUnselects[$name]);
        }
        
        $related = $query->get();
        
        $indexed = [];
        foreach ($related as $item) {
            $key = $item->$lookupKey;
            if ($type === 'hasMany') {
                $indexed[$key][] = $item;
            } else {
                if (!isset($indexed[$key])) {
                    $indexed[$key] = $item;
                }
            }
        }
        
        foreach ($models as $model) {
            $val = $model->$localKey ?? null;
            if ($type === 'hasMany') {
                $model->relations[$name] = $indexed[$val] ?? [];
            } else {
                $model->relations[$name] = $indexed[$val] ?? null;
            }
        }
    }
    
    protected function parseRelationDefinition(string $definition): array
    {
        if (strpos($definition, ':') === false) {
            return ['name' => $definition, 'localKey' => null, 'foreignKey' => '', 'type' => 'hasOne'];
        }
        [$name, $columnSpec] = explode(':', $definition, 2);
        $parts = explode(',', $columnSpec);
        
        return [
            'name' => $name,
            'localKey' => $parts[0] ?? null,
            'foreignKey' => $parts[1] ?? '',
            'type' => $parts[2] ?? 'hasOne',
        ];
    }
    
    protected function resolveRelatedClass($model, string $name): string
    {
        $className = ucfirst($name);
        $modelClass = get_class($model);
        $namespace = substr($modelClass, 0, strrpos($modelClass, '\\'));
        
        $fqcn = $namespace . '\\' . $className;
        if (class_exists($fqcn)) return $fqcn;
        
        $fqcn = 'App\\Models\\' . $className;
        if (class_exists($fqcn)) return $fqcn;
        
        throw new \RuntimeException("Cannot resolve related model class for relation '{$name}'");
    }

    // =========================================================
    // ARRAY & JSON CONVERSION
    // =========================================================

    public function toArray(): array
    {
        $array = $this->attributes;
        foreach ($this->hidden as $key) {
            unset($array[$key]);
        }
        
        foreach ($this->relations as $name => $value) {
            if ($value === null) {
                $array[$name] = null;
            } elseif (is_array($value)) {
                $array[$name] = array_map(function($item) {
                    return $item instanceof Model2 ? $item->toArray() : $item;
                }, $value);
            } elseif ($value instanceof self) {
                $array[$name] = $value->toArray();
            } else {
                $array[$name] = $value;
            }
        }
        return $array;
    }
    
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    // =========================================================
    // MAGIC METHODS & RESTORE
    // =========================================================

    public function restore(): bool
    {
        if (!$this->softDeletes) return false;
        $this->forceFill('deleted_at', null);
        return (bool)$this->save();
    }

    public function __get(string $key)
    {
        return $this->attributes[$key] ?? null;
    }
    
    public function __set(string $key, $value): void
    {
        if (in_array($key, $this->guarded)) return;
        if (!empty($this->fillable) && in_array($key, $this->fillable)) {
            $this->attributes[$key] = $value;
        }
    }
}
