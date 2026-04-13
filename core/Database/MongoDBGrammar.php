<?php

namespace Framework\Core\Database;

class MongoDBGrammar implements Grammar
{
    public function formatDate($value)
    {
        if ($value instanceof \DateTimeInterface) {
            return new \MongoDB\BSON\UTCDateTime($value->getTimestamp() * 1000);
        }

        // Only treat as timestamp if it looks like one (e.g. > year 2000 in seconds)
        if (is_numeric($value) && $value > 946684800) {
            return new \MongoDB\BSON\UTCDateTime($value * 1000);
        }

        // Handle string formats commonly used by the framework if necessary
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value)) {
            return new \MongoDB\BSON\UTCDateTime(strtotime($value) * 1000);
        }

        return $value;
    }

    public function wrap(string $value): string
    {
        // No SQL style wrapping for MongoDB
        return $value;
    }
}
