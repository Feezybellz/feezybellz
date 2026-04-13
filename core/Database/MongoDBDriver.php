<?php

namespace Framework\Core\Database;

use MongoDB\Driver\Manager;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Query;
use MongoDB\Driver\Command;
use MongoDB\BSON\ObjectId;

class MongoDBDriver implements DatabaseDriverInterface
{
    protected $manager = null;
    protected $databaseName = null;
    protected $lastInsertedId = null;
    protected $session = null; // For transactions
    protected $grammar = null;

    public function getGrammar(): Grammar
    {
        if (!$this->grammar) {
            $this->grammar = new MongoDBGrammar();
        }
        return $this->grammar;
    }


    public function connect(array $config): void
    {
        try {
            // Priority 1: Use the full URI if provided in the config
            if (!empty($config['uri'])) {
                $uri = $config['uri'];
            } else {
                // Priority 2: Build the URI from host/port components
                $uri = sprintf(
                    "mongodb://%s:%s@%s:%s",
                    $config['username'] ?? '',
                    $config['password'] ?? '',
                    $config['host'] ?? 'localhost',
                    $config['port'] ?? 27017
                );
                
                // Handle cases with no authentication
                if (empty($config['username'])) {
                    $uri = sprintf(
                        "mongodb://%s:%s",
                        $config['host'] ?? 'localhost',
                        $config['port'] ?? 27017
                    );
                }
            }
            
            $this->manager = new Manager($uri);
            $this->databaseName = $config['database'];
        } catch (\Exception $e) {
            throw new \Exception("MongoDB Connection failed: " . $e->getMessage());
        }
    }

    public function executeBuilder(QueryBuilder $builder)
    {
        $namespace = $this->databaseName . '.' . $builder->table;

        switch ($builder->operation) {
            case 'select':
                return $this->handleSelect($builder, $namespace);
            case 'insert':
                return $this->handleInsertWithId($builder, $namespace);
            case 'upsert':
                return $this->handleBatchWrite($builder, $namespace, 'upsert');
            case 'update':
                return $this->handleBatchWrite($builder, $namespace, 'update');
            case 'delete':
                return $this->handleBatchWrite($builder, $namespace, 'delete');
            case 'count':
                return $this->handleCount($builder);
            case 'sum':
                return $this->handleSum($builder);
            case 'avg':
                return $this->handleAvg($builder);
            default:
                throw new \Exception("Operation not supported by MongoDB driver: {$builder->operation}");
        }
    }

    private function handleAggregateSelect(QueryBuilder $builder): array
    {
        $pipeline = [];

        // 1. Match stage
        $filter = $this->parseWhereToFilter($builder->where);
        if (!empty($filter)) {
            $pipeline[] = ['$match' => (object)$filter];
        }

        // 2. Lookup stages (Joins)
        foreach ($builder->joins as $join) {
            // Basic mapping of SQL join to MongoDB lookup
            // Assumption: first is localField (table.col), second is foreignField (table.col)
            // We need to strip the table prefix for MongoDB
            $localField = str_contains($join['first'], '.') ? explode('.', $join['first'])[1] : $join['first'];
            $foreignField = str_contains($join['second'], '.') ? explode('.', $join['second'])[1] : $join['second'];
            
            if ($localField === 'id') $localField = '_id';
            if ($foreignField === 'id') $foreignField = '_id';

            $pipeline[] = [
                '$lookup' => [
                    'from'         => $join['table'],
                    'localField'   => $localField,
                    'foreignField' => $foreignField,
                    'as'           => "joined_{$join['table']}"
                ]
            ];

            // For INNER/LEFT joins, we often want to unwind if it's a 1:1 relation
            // To be safe and polyglot-friendly, we'll unwind but preserve nulls for LEFT JOIN
            $preserveNulls = (strtoupper($join['type']) === 'LEFT');
            $pipeline[] = [
                '$unwind' => [
                    'path' => "\$joined_{$join['table']}",
                    'preserveNullAndEmptyArrays' => $preserveNulls
                ]
            ];
        }

        // 3. Sort stage
        if (!empty($builder->orderBy)) {
            $sort = [];
            foreach ($builder->orderBy as $order) {
                $sort[$order['column']] = strtolower($order['direction']) === 'desc' ? -1 : 1;
            }
            $pipeline[] = ['$sort' => $sort];
        }

        // 4. Limit/Skip
        if ($builder->offset !== null) {
            $pipeline[] = ['$skip' => $builder->offset];
        }
        if ($builder->limit !== null) {
            $pipeline[] = ['$limit' => $builder->limit];
        }

        // 5. Projection
        $projection = [];
        if ($builder->select !== ['*']) {
            foreach ($builder->select as $col) {
                $projection[$col] = 1;
            }
        }
        if (!empty($builder->unselect)) {
            foreach ($builder->unselect as $col) {
                $projection[$col] = 0;
            }
        }
        if (!empty($projection)) {
            $pipeline[] = ['$project' => $projection];
        }

        $result = $this->executeCommand([
            'aggregate' => $builder->table,
            'pipeline' => $pipeline,
            'cursor' => new \stdClass,
        ]);

        return is_array($result) ? $result : iterator_to_array($result);
    }

    private function handleSelect(QueryBuilder $builder, string $namespace): array
    {
        if (!empty($builder->joins)) {
            return $this->handleAggregateSelect($builder);
        }
        
        $filter = $this->parseWhereToFilter($builder->where);

        $options = [];
        
        // --- Polyglot Projection Logic ---
        $projection = [];
        
        // 1. Inclusions
        if ($builder->select !== ['*']) {
            foreach ($builder->select as $col) {
                $projection[$col] = 1;
            }
        }
        
        // 2. Exclusions (Unselect)
        if (!empty($builder->unselect)) {
            foreach ($builder->unselect as $col) {
                $projection[$col] = 0; // 0 tells MongoDB to omit this field
            }
        }
        
        if (!empty($projection)) {
            $options['projection'] = $projection;
        }
        // ---------------------------------

        if ($builder->limit !== null) {
            $options['limit'] = $builder->limit;
        }
        if ($builder->offset !== null) {
            $options['skip'] = $builder->offset;
        }
        if (!empty($builder->orderBy)) {
            $sort = [];
            foreach ($builder->orderBy as $order) {
                $sort[$order['column']] = strtolower($order['direction']) === 'desc' ? -1 : 1;
            }
            $options['sort'] = $sort;
        }

        $query = new \MongoDB\Driver\Query($filter, $options);
        $cursor = $this->manager->executeQuery($namespace, $query);
return iterator_to_array($cursor);
    }


    private function handleCount(QueryBuilder $builder): int
    {
        $filter = $this->parseWhereToFilter($builder->where);
        
        $result = $this->executeCommand([
            'count' => $builder->table,
            'query' => (object) $filter 
        ]);
        
        return $result->n ?? 0;
    }

    private function handleInsertWithId(QueryBuilder $builder, string $namespace)
    {
        // If it's a single row of data
        if (!isset($builder->data[0])) {
            return $this->insert($builder->table, $builder->data);
        }

        // For batch inserts, return the count from the existing helper
        return $this->handleBatchWrite($builder, $namespace, 'insert');
    }
    private function handleBatchWrite(QueryBuilder $builder, string $namespace, string $type): int
    {
        $bulk = new BulkWrite();
        $rows = isset($builder->data[0]) ? $builder->data : [$builder->data];

        if ($type === 'delete') {
            $filter = $this->parseWhereToFilter($builder->where);
            // Fix: Cast filter to (object)
            $bulk->delete((object)$filter, ['limit' => 0]); 
            $result = $this->manager->executeBulkWrite($namespace, $bulk);
            return $result->getDeletedCount();
        }

        if ($type === 'update') {
            $filter = $this->parseWhereToFilter($builder->where);
            // Fix: Cast filter to (object)
            $bulk->update((object)$filter, ['$set' => $builder->data], ['multi' => true]);
            $result = $this->manager->executeBulkWrite($namespace, $bulk);
            return $result->getModifiedCount();
        }

        foreach ($rows as $row) {
            if ($type === 'insert') {
                $bulk->insert($row);
            } elseif ($type === 'upsert') {
                $filter = [];
                foreach ($builder->uniqueBy as $key) {
                    $filter[$key] = $row[$key] ?? null;
                }
                $bulk->update($filter, ['$set' => $row], ['upsert' => true]);
            }
        }

        $result = $this->manager->executeBulkWrite($namespace, $bulk);
        switch ($type) {
            case 'insert':
                return $result->getInsertedCount();
            case 'upsert':
                return $result->getUpsertedCount() + $result->getModifiedCount();
            default:
                return 0;
        }
    }

    private function handleSum(QueryBuilder $builder): float
    {
        // The column to sum is passed through the data property or a specific property in builder
        $column = $builder->aggregateColumn; 
        $filter = $this->parseWhereToFilter($builder->where);

        $pipeline = [
            ['$match' => (object)$filter],
            ['$group' => [
                '_id' => null,
                'total' => ['$sum' => '$' . $column]
            ]]
        ];

        $result = $this->executeCommand([
            'aggregate' => $builder->table,
            'pipeline' => $pipeline,
            'cursor' => new \stdClass,
        ]);

        return (float)($result->total ?? 0);
    }

    private function handleAvg(QueryBuilder $builder): float
    {
        $column = $builder->aggregateColumn; 
        $filter = $this->parseWhereToFilter($builder->where);

        $pipeline = [
            ['$match' => (object)$filter],
            ['$group' => [
                '_id' => null,
                'avg' => ['$avg' => '$' . $column]
            ]]
        ];

        $result = $this->executeCommand([
            'aggregate' => $builder->table,
            'pipeline' => $pipeline,
            'cursor' => new \stdClass,
        ]);

        return (float)($result->avg ?? 0);
    }


    private function parseWhereToFilter(array $whereClauses): array
    {
        if (empty($whereClauses)) return [];
        
        $filter = [];
        foreach ($whereClauses as $w) {
            if (isset($w['type']) && $w['type'] === 'raw') {
                error_log("MongoDB Driver: whereRaw is not fully supported and was ignored: " . $w['sql']);
                continue;
            }

            $op = strtoupper($w['operator'] ?? '=');
            $col = $w['column'];
            $val = $w['value'];
            
            // POLYGLOT FIX: Map 'id' to '_id' for MongoDB
            if ($col === 'id') { $col = '_id'; }

            // Convert 24-char strings to ObjectId for _id OR any foreign key ending in _id
            $is_id_column = ($col === '_id' || (strlen($col) >= 3 && substr($col, -3) === '_id'));
            if ($is_id_column && is_string($val) && strlen($val) === 24) {
                try {
                    $val = new \MongoDB\BSON\ObjectId($val);
                } catch (\Exception $e) { /* keep as string */ }
            }
            
            // --- CUSTOM ENGINE OPERATORS (DATE, YEAR, MONTH) ---
            if ($op === 'DATE') {
                // Converts "YYYY-MM-DD" to a range matching the whole day in a timestamp string
                $filter[$col] = [
                    '$gte' => $val . ' 00:00:00',
                    '$lte' => $val . ' 23:59:59'
                ];
                continue; // Prevent standard operator match
            } 
            
            if ($op === 'YEAR') {
                // Matches "YYYY-..." at the start of the string
                $filter[$col] = ['$regex' => '^' . $val . '-', '$options' => 'i'];
                continue;
            } 
            
            if ($op === 'MONTH') {
                // Matches "-MM-" segment in timestamp strings (e.g., -02-)
                $month = str_pad((string)$val, 2, '0', STR_PAD_LEFT);
                $filter[$col] = ['$regex' => '-' . $month . '-', '$options' => 'i'];
                continue;
            }

            // --- STANDARD OPERATORS ---
            switch ($op) {
                case '=':
                    $mongoOp = '$eq';
                    break;
                case '>':
                    $mongoOp = '$gt';
                    break;
                case '<':
                    $mongoOp = '$lt';
                    break;
                case '>=':
                    $mongoOp = '$gte';
                    break;
                case '<=':
                    $mongoOp = '$lte';
                    break;
                case '!=':
                    $mongoOp = '$ne';
                    break;
                case 'IN':
                    $mongoOp = '$in';
                    break;
                case 'NOT IN':
                    $mongoOp = '$nin';
                    break;
                case 'LIKE':
                    $mongoOp = '$regex';
                    break;
                default:
                    $mongoOp = '$eq';
            }

            // SAFETY GUARD FOR $in
            if (($op === 'IN' || $op === 'NOT IN') && !is_array($val)) {
                $val = [$val]; // Wrap in array to prevent MongoDB driver crash
            } elseif ($op === 'LIKE') {
                $filter[$col] = ['$regex' => str_replace('%', '', $val), '$options' => 'i'];
            } elseif ($op === 'IS NULL') {
                $filter[$col] = null;
            } elseif ($op === 'IS NOT NULL') {
                $filter[$col] = ['$ne' => null];
            } elseif ($op === 'BETWEEN' && is_array($val)) {
                $filter[$col] = [
                    '$gte' => $this->getGrammar()->formatDate($val[0]), 
                    '$lte' => $this->getGrammar()->formatDate($val[1])
                ];
            } else {
                $val = $this->getGrammar()->formatDate($val);
                // Support multiple conditions on the same column
                if (isset($filter[$col]) && is_array($filter[$col])) {
                    $filter[$col] = array_merge($filter[$col], [$mongoOp => $val]);
                } else {
                    $filter[$col] = [$mongoOp => $val];
                }
            }
        }
        return $filter;
    }

    private function parseWhere(QueryBuilder $builder): array
    {
        $where = [];
        foreach ($builder->where as $w) {
            $where[$w['column']] = $w['value'];
        }
        return $where;
    }

    public function query(string $query, array $params = [])
    {
        // For MongoDB, raw queries via interface are generally not used or fallback to command
        throw new \Exception("Raw string queries are not supported in MongoDBDriver. Use QueryBuilder.");
    }

    public function insert(string $table, array $data)
    {
        $namespace = $this->databaseName . '.' . $table;
        $bulk = new BulkWrite();
        
        $id = new ObjectId();
        if (!isset($data['_id'])) {
            $data['_id'] = $id;
        }
        
        foreach ($data as $key => &$value) {
            $value = $this->getGrammar()->formatDate($value);
        }
        
        $bulk->insert($data);
        $this->manager->executeBulkWrite($namespace, $bulk);
        
        $this->lastInsertedId = (string) $data['_id'];
        return $this->lastInsertedId;
    }

    public function update(string $table, array $data, array $where)
    {
        $namespace = $this->databaseName . '.' . $table;
        $bulk = new BulkWrite();
        
        if (isset($where['_id']) && is_string($where['_id'])) {
            $where['_id'] = new ObjectId($where['_id']);
        }
        
        foreach ($data as $key => &$value) {
            $value = $this->getGrammar()->formatDate($value);
        }

        foreach ($where as $key => &$value) {
            $value = $this->getGrammar()->formatDate($value);
        }
        
        $bulk->update($where, ['$set' => $data], ['multi' => true]);
        
        $result = $this->manager->executeBulkWrite($namespace, $bulk);
        return $result->getModifiedCount();
    }

    public function delete(string $table, array $where)
    {
        $namespace = $this->databaseName . '.' . $table;
        $bulk = new BulkWrite();
        
        if (isset($where['_id']) && is_string($where['_id'])) {
            $where['_id'] = new ObjectId($where['_id']);
        }
        
        $bulk->delete($where, ['limit' => 0]);
        
        $result = $this->manager->executeBulkWrite($namespace, $bulk);
        return $result->getDeletedCount();
    }

    public function lastInsertId()
    {
        return $this->lastInsertedId;
    }

    public function isConnected(): bool
    {
        return $this->manager !== null;
    }

    public function createStorage(Schema $schema): void
    {
        $indexesToCreate = [];

        // 1. Convert Foreign Keys to Indexes
        // In NoSQL, a foreign key is just a field we plan to query often.
        foreach ($schema->foreignKeys as $fk) {
            $indexesToCreate[] = [
                'name' => "fk_idx_{$fk['column']}",
                'key'  => [$fk['column'] => 1],
            ];
        }

        // 2. Process Standard Indexes
        foreach ($schema->indexes as $index) {
            $keys = [];
            foreach ($index['columns'] as $column) {
                $keys[$column] = 1; 
            }
            $indexesToCreate[] = [
                'name' => $index['name'],
                'key'  => $keys,
            ];
        }

        // 3. Process Unique Indexes
        foreach ($schema->uniqueIndexes as $index) {
            $keys = [];
            foreach ($index['columns'] as $column) {
                $keys[$column] = 1;
            }
            $indexesToCreate[] = [
                'name'   => $index['name'],
                'key'    => $keys,
                'unique' => true,
            ];
        }

        // 4. Batch Create Indexes
        if (!empty($indexesToCreate)) {
            try {
                $this->executeCommand([
                    'createIndexes' => $schema->table,
                    'indexes'       => $indexesToCreate,
                ]);
            } catch (\Exception $e) {
                // MongoDB throws if an index with the same name exists but different options
            }
        }
    }

    

    public function alterStorage(Schema $schema): void
    {
        // MongoDB is schemaless, so altering storage (columns) is not needed.
    }

    public function dropStorage(string $name): void
    {
        // Drop the collection via the manager command
        $command = new \MongoDB\Driver\Command(['drop' => $name]);
        try {
            $this->manager->executeCommand($this->databaseName, $command);
        } catch (\Exception $e) {
            // Silently fail if collection doesn't exist
        }
    }

    public function ensureMigrationTracking(string $tableName): void
    {
        // Not needed for MongoDB as it's schemaless
    }
    public function beginTransaction(): void
    {
        $this->session = $this->manager->startSession();
        $this->session->startTransaction();
    }

    public function commit(): void
    {
        if ($this->session) {
            $this->session->commitTransaction();
            $this->session = null;
        }
    }

    public function rollBack(): void
    {
        if ($this->session) {
            $this->session->abortTransaction();
            $this->session = null;
        }
    }

    /**
     * Helper to execute a MongoDB command with unified error handling.
     */
    private function executeCommand(array $payload)
    {
        try {
            $command = new \MongoDB\Driver\Command($payload);
            $cursor = $this->manager->executeCommand($this->databaseName, $command);
            return current($cursor->toArray());
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            // Log the error or throw a framework-specific exception
            throw new \Exception("MongoDB Driver Error: " . $e->getMessage());
        }
    }
}
