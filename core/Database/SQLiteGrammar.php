<?php

namespace Framework\Core\Database;

class SQLiteGrammar extends Grammar
{
    protected string $quote = '"';

    public function formatDate($value)
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_numeric($value) && $value > 946684800) {
            return date('Y-m-d H:i:s', (int)$value);
        }

        return $value;
    }
}
