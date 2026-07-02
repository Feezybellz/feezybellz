<?php

namespace Framework\Core\Database;

class MongoDBGrammar extends Grammar
{
    public function formatDate($value)
    {
        if ($value instanceof \DateTimeInterface) {
            return new \MongoDB\BSON\UTCDateTime($value->getTimestamp() * 1000);
        }

        if (is_numeric($value) && $value > 946684800) {
            return new \MongoDB\BSON\UTCDateTime($value * 1000);
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value)) {
            return new \MongoDB\BSON\UTCDateTime(strtotime($value) * 1000);
        }

        return $value;
    }

    /**
     * Mongo uses no SQL-style quoting. Field names are validated only when
     * they cross into a query context where injection-like semantics matter.
     */
    public function wrap(string $value): string
    {
        return trim($value);
    }

    public function wrapTable(string $table): string
    {
        return trim($table);
    }
}
