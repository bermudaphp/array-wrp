<?php

namespace Bermuda\Stdlib;

use Traversable;
use ReturnTypeWillChange;

class ArrayWrp implements \ArrayAccess, Arrayable, \IteratorAggregate, \Countable
{
    protected array $data = [];
    
    public function __construct(iterable|object $data = []) {
        if (is_array($data)) $this->data = $data;
        else if (is_object($data)) $this->data = $data instanceof Arrayable ? $data->toArray() : get_object_vars($data);
        else foreach ($data as $offset => $datum) $this->data[$offset] = $datum;
    }

    public static function fromKeys(iterable|object $data): static
    {
        if (is_object($data)) $data = $data instanceof Arrayable ? $data->toArray() : get_object_vars($data);
        $static = new static;
        foreach ($data as $k => $v) $static->data[] = $k;
        return $static;
    }

    public static function fromValues(iterable|object $data): static
    {
        if (is_object($data)) $data = $data instanceof Arrayable ? $data->toArray() : get_object_vars($data);
        $static = new static;
        foreach ($data as $v) $static->data[] = $v;
        return $static;
    }

    public static function explode(string $string, string $separator = ','): static
    {
        return new static(explode($separator, $string));
    }

    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    public function reject(callable $callback, bool $recursive = false): static
    {
        $data = [];
        foreach ($this->data as $offset => $datum) {
            if ($recursive && is_array($datum)) {
                $datum = (new static($datum))->reject($callback, true)->data;
                if (!empty($datum)) $data[$offset] = $datum;
            } elseif (!$callback($datum, $offset)) {
                $data[$offset] = $datum;
            }
        }

        return $this->replace($data);
    }

    public function search(mixed $needle, bool $strict = false): int|string|null
    {
        if (is_callable($needle)) {
            foreach ($this->data as $offset => $datum) {
                if ($needle($datum)) {
                    return $offset;
                }
            }
        }

        foreach ($this->data as $offset => $datum) {
            if ($strict ? $datum === $needle : $datum == $needle) return $offset;
        }

        return null;
    }

    public function searchAll(mixed $needle, bool $strict = false): array
    {
        $keys = [];
        if (is_callable($needle)) {
            foreach ($this->data as $offset => $datum) {
                if ($needle($datum)) {
                    $keys[] = $offset;
                }
            }

            return $keys;
        }

        foreach ($this->data as $offset => $datum) {
            if ($strict ? $datum === $needle : $datum == $needle) $keys[] = $offset;
        }

        return $keys;
    }

    public function reduce(callable $callback, mixed $initial = null, bool $recursive = false): mixed
    {
        $carry = $initial;
        foreach ($this->data as $datum) {
            if (is_array($datum) && $recursive) {
                $carry = (new static)->reduce($callback, $carry, true);
            } else {
                $carry = $callback($carry, $datum);
            }
        }

        return $carry;
    }

    public function clear(): static
    {
        return $this->replace([]);
    }

    public function flip(): static
    {
        return $this->replace(array_flip($this->data));
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function pull(string|int $offset, mixed $default = null): mixed
    {
        if ($this->offsetExists($offset)) {
            $value = $this->offsetGet($offset);
            $this->offsetUnset($offset);

            return $value;
        }

        return $default;
    }

    /**
     * @param string|int $offset
     * @param string|int $newOffset
     * @return $this
     */
    public function offsetReplace(string|int $offset, string|int $newOffset): static
    {
        if ($this->offsetExists($offset)) {
            $this->offsetSet($newOffset, $this->pull($offset));
        }

        return $this;
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    public function each(callable $callback): ArrayWrp
    {
        $static = new static;
        foreach ($this->data as $k => $value) {
            $static->data[$k] = $callback($value, $k);
        }

        return $static;
    }
    
    public function fit(callable $callback): bool
    {
        $stop = false;
        foreach ($this->data as $k => $value) {
            if (!$callback($value, $k, $stop)) return false;
            if ($stop) return true;
        }

        return true;
    }

    public function key(): int|string|null
    {
        return key($this->data);
    }

    public function current(): mixed
    {
        return current($this->data);
    }

    public function end(): mixed
    {
        return end($this->data);
    }

    public function prev(): mixed
    {
        return prev($this->data);
    }

    public function next(): mixed
    {
        return next($this->data);
    }

    public function rewind(): void
    {
        reset($this->data);
    }

    public function intersectKeys(iterable $iterable): static
    {
        $data = [];
        foreach ($iterable as $key => $v) {
            if ($this->offsetExists($key)) $data[$key] = $this->data[$key];
        }

        return $this->replace($data);
    }

    public function values(): static
    {
        return $this->replace(array_values($this->data));
    }

    /**
     * @param mixed $offset
     * @return mixed|static
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!$this->offsetExists($offset)) {
            return $this->data[$offset] = new static;
        }

        $v = $this->data[$offset];
        return is_array($v) ? new static($v) : $v;
    }

    /**
     * @param string|int $offset
     * @param mixed|null $default
     * @return mixed|static
     */
    public function get(string|int $offset, mixed $default = null): mixed
    {
        return $this->offsetExists($offset) ? $this->data[$offset] : $default;
    }


    public function lastKey(): int|string|null
    {
        return array_key_last($this->data);
    }

    public function firstKey(): int|string|null
    {
        return array_key_first($this->data);
    }

    public function latsItem(): mixed
    {
        return $this->data[$this->lastKey()] ?? null;
    }

    public function firstItem(): mixed
    {
        return $this->data[$this->firstKey()] ?? null;
    }

    public function keys(): static
    {
        return new static(array_keys($this->data));
    }

    public function except(string|int ... $keys): static
    {
        $data = $this->data;
        foreach (array_diff_key(array_keys($data, $keys)) as $key) {
            unset($data[$key]);
        }

        return $this->replace($data);
    }

    public function only(string|int ... $keys): static
    {
        $only = [];
        foreach ($keys as $key) {
            if ($this->offsetExists($key)) $only[$key] = $this->data[$key];
        }

        return $this->replace($only);
    }

    public function reverse(bool $preserveKeys = false, bool $recursive = false): static
    {
        $data = $this->data;
        if ($recursive) {
            $data = $this->map(function($value) use ($preserveKeys) {
                return is_array($value) ? (new static($value))->reverse($preserveKeys, true)->data : $value;
            })->data;
        }

        return $this->replace(array_reverse($data));
    }


    public function map(callable $callback, bool $recursive = false): static
    {
        $data = [];
        foreach ($this->data as $key => $datum) {
            if ($recursive && is_array($datum)) {
                $data[$key] = (new static($datum))->map($callback, true)->data;
            } else {
                $data[$key] = $callback($datum, $key);
            }
        }

        return $this->replace($data);
    }

    /**
     * @param string $glue
     * @param bool $ignoreNotString
     * @return string
     * @throws \Error
     */
    public function implode(string $glue = ',', bool $recursive = false, bool $ignoreNotString = true): string
    {
        $str = '';
        foreach ($this->data as $datum) {
            try {
                if (is_array($datum)) {
                    $str .= (new static($datum))->implode($glue, $recursive, $ignoreNotString);
                    continue;
                }

                $str .= "$glue$datum";
            } catch (\Throwable) {
                if (!$ignoreNotString) throw new \Error("Element with key [".key($this->data)."] could not be converted to string");
                continue;
            }
        }

        return ltrim($str, $glue);
    }

    public function filter(callable $callback, bool $recursive = false): static
    {
        $filtered = [];
        if ($recursive) {
            foreach ($this->data as $offset => $datum) {
                if (is_array($datum)) {
                    $datum = (new static($datum))->filter($callback, true)->data;
                    if (!empty($datum)) $filtered[$offset] = $datum;
                }

                else if ($callback($datum, $offset) === true) {
                    $filtered[$offset] = $datum;
                }
            }
        } else {
            foreach ($this->data as $offset => $datum) {
                if ($callback($datum, $offset) === true) $filtered[$offset] = $datum;
            }
        }

        return $this->replace($filtered);
    }
    
    public function transform(callable $callback, bool $recursive = false): static
    {
        $data = [];
        foreach ($this->data as $k => $value) {
            if ($recursive && is_array($value)) {
                $value = (new static($value))->transform($callback, true);
                if (!$value->isEmpty()) $data[$k] = $value->toArray();
                continue;
            }

            if (($result = $callback($value, $k)) !== null) $data[$result[0]] = $result[1];
        }

        return $this->replace($data);
    }

    public function fetch(string|int $key): static
    {
        foreach ($this->data as $n => $value) if (is_array($value)) $data[$n] = $value[$key];
        return new static($data ?? []);
    }
    
    #[ReturnTypeWillChange] public function offsetSet(mixed $offset, mixed $value): static
    {
        !is_iterable($value) ?: $value = new static($value);
        $offset === null ? $this->data[] = $value : $this->data[$offset] = $value;
        return $this;
    }

    #[ReturnTypeWillChange] public function offsetUnset(mixed $offset): static
    {
        unset($this->data[$offset]);
        return $this;
    }

    public function getIterator(): Traversable
    {
        foreach ($this->data as $offset => $datum) yield $offset => is_array($datum)
            ? new static($datum) : $datum;
    }

    public function replace(iterable $data): static
    {
        if (is_array($data)) {
            $this->data = $data;
        } else {
            $this->data = [];
            foreach ($data as $offset => $datum) {
                $this->data[$offset] = $datum;
            }
        }

        return $this;
    }


    /**
     * @param bool $recursive
     * @return int
     */
    public function count(bool $recursive = false): int
    {
        if ($recursive) {
            $count = 0;
            foreach ($this->data as $datum) {
                $count += is_array($datum) ? (new static($datum))->count(true) : 1;
            }

            return $count;
        }

        return count($this->data);
    }
}
