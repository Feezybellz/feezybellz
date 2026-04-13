<?php

namespace Framework\Core\Database;

interface Grammar
{
    /**
     * Format a date for the database
     * 
     * @param mixed $value
     * @return string|int|mixed
     */
    public function formatDate($value);

    /**
     * Wrap an identifier (table or column)
     * 
     * @param string $value
     * @return string
     */
    public function wrap(string $value): string;
}
