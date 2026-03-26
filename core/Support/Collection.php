<?php

namespace Framework\Core\Support;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;

class Collection implements ArrayAccess, Countable, IteratorAggregate
{
    protected $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public static function make(array $items = []): self
    {
        return new static($items);
    }

    // MAP: Transform each item
    public function map(callable $callback): self
    {
        return new static(array_map($callback, $this->items));
    }

    // FILTER: Keep items that pass a truth test
    public function filter(callable $callback = null): self
    {
        if ($callback) {
            return new static(array_filter($this->items, $callback));
        }
        return new static(array_filter($this->items));
    }

    // PLUCK: Extract an array of values for a given key
    public function pluck(string $key): self
    {
        return $this->map(fn($item) => is_array($item) ? ($item[$key] ?? null) : ($item->$key ?? null));
    }

    // FIRST: Get the first item
    public function first(callable $callback = null)
    {
        $arr = $callback ? $this->filter($callback)->toArray() : $this->items;
        return $arr[array_key_first($arr)] ?? null;
    }

    public function toArray(): array
    {
        return array_map(function ($value) {
            return $value instanceof Collection ? $value->toArray() : $value;
        }, $this->items);
    }

    // Interface implementations to make it behave like an array
    public function count(): int { return count($this->items); }
    public function getIterator(): ArrayIterator { return new ArrayIterator($this->items); }
    public function offsetExists($offset): bool { return isset($this->items[$offset]); }
    public function offsetGet($offset) { return $this->items[$offset]; }
    public function offsetSet($offset, $value): void { $this->items[$offset] = $value; }
    public function offsetUnset($offset): void { unset($this->items[$offset]); }
}
