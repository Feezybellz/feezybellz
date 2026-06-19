<?php

namespace Framework\Core\Database;

class PostgreSQLGrammar implements Grammar
{
    public function formatDate($value)
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        // Only treat as timestamp if it looks like one (e.g. > year 2000 in seconds)
        if (is_numeric($value) && $value > 946684800) {
            return date('Y-m-d H:i:s', (int)$value);
        }

        return $value;
    }

    public function wrap(string $value): string
    {
        $value = trim($value);
        if ($value === '*' || strpos($value, '(') !== false || strpos($value, '"') !== false) {
            return $value;
        }

        if (strpos($value, '.') !== false) {
            $parts = explode('.', $value);
            $table = trim($parts[0]);
            $col = trim($parts[1]);
            return $col === '*' ? "\"{$table}\".*" : "\"{$table}\".\"{$col}\"";
        }

        return "\"{$value}\"";
    }
}
