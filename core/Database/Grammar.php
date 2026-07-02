<?php

namespace Framework\Core\Database;

/**
 * Base class for database grammars.
 *
 * Each driver ships its own grammar that fills in the quoting character and
 * date formatting. Identifier validation lives here, in one place, so every
 * driver gets the same SQL-injection defence at the table/column boundary.
 */
abstract class Grammar
{
    /**
     * Strict identifier pattern: bare name or table.column.
     * Allows alphanumeric + underscore. No spaces, parens, operators, or dots
     * beyond a single table-prefix segment.
     */
    protected const IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/';

    /**
     * Bare table-name pattern (no dot-prefix permitted).
     */
    protected const TABLE_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * Character used to quote identifiers in this dialect.
     * Override in subclass. (` for MySQL, " for Postgres/SQLite, none for Mongo.)
     */
    protected string $quote = '"';

    /**
     * Format a value for storage. Drivers convert DateTimeInterface,
     * unix timestamps, etc. to their canonical wire format.
     */
    abstract public function formatDate($value);

    /**
     * Wrap an identifier (column, table, or table.column) for use in SQL.
     *
     * Accepts:
     *   - `*`           → returned as-is (SELECT * is always permitted)
     *   - `table.*`     → returned as `"table".*`
     *   - `table.col`   → returned as `"table"."col"`
     *   - `col`         → returned as `"col"`
     *
     * Any identifier already containing the quote character or a paren is
     * assumed to be pre-wrapped or a raw expression and is returned untouched
     * (caller's responsibility). Anything else is validated against
     * IDENTIFIER_PATTERN and rejected with an exception on mismatch — this is
     * what stops `orderBy($_GET['sort'])` from injecting.
     */
    public function wrap(string $value): string
    {
        $value = trim($value);

        if ($value === '*' || $value === '') {
            return $value === '' ? $value : '*';
        }

        // Already-wrapped or raw expression — caller is on the hook.
        if (strpos($value, $this->quote) !== false || strpos($value, '(') !== false) {
            return $value;
        }

        if (strpos($value, '.') !== false) {
            [$table, $col] = array_map('trim', explode('.', $value, 2));
            $this->assertValidPart($table);
            if ($col === '*') {
                return $this->quote . $table . $this->quote . '.*';
            }
            $this->assertValidPart($col);
            return $this->quote . $table . $this->quote . '.' . $this->quote . $col . $this->quote;
        }

        $this->assertValidPart($value);
        return $this->quote . $value . $this->quote;
    }

    /**
     * Wrap a plain table name (no dot prefix allowed). Use this anywhere a
     * raw `FROM`/`INTO` clause is being built so the table can never include
     * a smuggled expression.
     */
    public function wrapTable(string $table): string
    {
        $table = trim($table);
        if (!preg_match(self::TABLE_PATTERN, $table)) {
            throw new \InvalidArgumentException("Invalid table name: '{$table}'");
        }
        return $this->quote . $table . $this->quote;
    }

    /**
     * Validate (but do not wrap) a single identifier part.
     * Useful when a driver needs the raw name (e.g., to build a sequence name).
     */
    public function validateIdentifier(string $name): string
    {
        $this->assertValidPart($name);
        return $name;
    }

    private function assertValidPart(string $part): void
    {
        if (!preg_match(self::TABLE_PATTERN, $part)) {
            throw new \InvalidArgumentException("Invalid identifier: '{$part}'");
        }
    }
}
