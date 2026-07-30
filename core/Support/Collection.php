<?php

namespace Framework\Core\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

class Collection implements IteratorAggregate, Countable, JsonSerializable, ArrayAccess
{
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public static function make(array $items = []): self
    {
        return new static($items);
    }

    public function all(): array
    {
        return $this->items;
    }

    public function map(callable $callback): self
    {
        $keys = array_keys($this->items);
        $items = array_map($callback, $this->items, $keys);
        
        return new static(array_combine($keys, $items));
    }

    public function filter(callable $callback = null): self
    {
        if ($callback) {
            return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
        }

        return new static(array_filter($this->items));
    }

    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    public function pluck(string $value, string $key = null): self
    {
        $results = [];

        foreach ($this->items as $item) {
            $itemValue = is_object($item) ? ($item->$value ?? null) : ($item[$value] ?? null);

            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = is_object($item) ? ($item->$key ?? null) : ($item[$key] ?? null);
                $results[$itemKey] = $itemValue;
            }
        }

        return new static($results);
    }

    public function first(callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            if (empty($this->items)) {
                return $default;
            }
            return reset($this->items);
        }

        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    public function last(callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return empty($this->items) ? $default : end($this->items);
        }

        return $this->reverse()->first($callback, $default);
    }

    public function reverse(): self
    {
        return new static(array_reverse($this->items, true));
    }

    public function push($value): self
    {
        $this->items[] = $value;
        return $this;
    }

    public function merge($items): self
    {
        $itemsArray = $items instanceof self ? $items->all() : (array) $items;
        return new static(array_merge($this->items, $itemsArray));
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function toArray(): array
    {
        return array_map(function ($value) {
            return $value instanceof self ? $value->toArray() : $value;
        }, $this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    // ArrayAccess methods
    public function offsetExists($offset): bool { return isset($this->items[$offset]); }
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) { return $this->items[$offset] ?? null; }
    public function offsetSet($offset, $value): void {
        if (is_null($offset)) { $this->items[] = $value; } else { $this->items[$offset] = $value; }
    }
    public function offsetUnset($offset): void { unset($this->items[$offset]); }
}
