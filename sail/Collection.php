<?php

namespace SailCMS;

use SailCMS\Types\Sorting;
use SailCMS\Text;

class Collection implements \JsonSerializable, \Iterator
{
    private array $_internal;

    /**
     *
     * Initialize a static with a root array
     *
     * @param array $baseValue
     * @param bool $recursive
     *
     */
    public function __construct(array $baseValue = [], bool $recursive = true)
    {
        if ($recursive) {
            foreach ($baseValue as $key => $value) {
                if (is_array($value)) {
                    $baseValue[$key] = new static($value);
                }
            }

            $this->_internal = $baseValue;
        } else {
            $this->_internal = $baseValue;
        }
    }

    /**
     *
     * Getter for the length property
     *
     * @param $property
     * @return mixed
     *
     */
    public function __get($property): mixed
    {
        return match ($property) {
            'length' => count($this->_internal),
            'first' => reset($this->_internal),
            'last' => end($this->_internal),
            'empty' => count($this->_internal) === 0,
            default => $this->_internal[$property],
        };
    }

    /**
     *
     * Setter for statics that are key => value based
     *
     * @param $property
     * @param $value
     * @return void
     *
     */
    public function __set($property, $value): void
    {
        $reserved = ['length', 'first', 'last'];

        if (!in_array($property, $reserved, true)) {
            $this->_internal[$property] = $value;
        }
    }

    /**
     *
     * Check if property exists
     *
     * @param $property
     * @return bool
     *
     */
    public function __isset($property): bool
    {
        return isset($this->_internal[$property]);
    }

    /**
     *
     * Unwrap a static back to a raw array
     *
     * @return array
     *
     */
    public function unwrap(): array
    {
        // Unwrap the static back to raw array recursively
        $arr = [];

        foreach ($this->_internal as $key => $value) {
            if ($value instanceof static) {
                $v = $value->unwrap();
                $arr[$key] = $v;
            } else {
                $arr[$key] = $value;
            }
        }

        return $arr;
    }

    /**
     *
     * Push a new element at the end of the static
     *
     * @param mixed $element
     * @return $this
     *
     */
    public function push(mixed $element): static
    {
        $this->_internal[] = $element;
        return $this;
    }

    /**
     *
     * Push an array or statics into seperate elements in the static
     *
     * @param ...$elements
     * @return $this
     *
     */
    public function pushSpread(...$elements): static
    {
        foreach ($elements as $element) {
            $this->_internal[] = $element;
        }

        return new static($this->_internal);
    }

    /**
     *
     * Add an element to the beginning of the static
     *
     * @param mixed $element
     * @return $this
     */
    public function prepend(mixed $element): static
    {
        array_unshift($this->_internal, $element);
        return $this;
    }

    /**
     *
     * Add an element to associative static with given key
     *
     * @param string $key
     * @param mixed $element
     * @return $this
     *
     */
    public function add(string $key, mixed $element): static
    {
        $this->_internal[$key] = $element;
        return $this;
    }

    /**
     *
     * Get a new static with the content of this static but reversed
     *
     * @return static
     *
     */
    public function reverse(): static
    {
        $arr = array_reverse($this->_internal);
        return new static($arr);
    }

    /**
     *
     * Get a slice of the static
     *
     * @param int $start
     * @param int $end
     * @return static
     *
     */
    public function slice(int $start, int $end): static
    {
        $arr = array_slice($this->_internal, $start, $end);
        return new static($arr);
    }

    /**
     *
     * Get and remove the last N items from the collection
     *
     * @param int $count
     * @return $this
     *
     */
    public function pop(int $count): static
    {
        if ($this->empty) {
            return new static([]);
        }

        if ($count === 1) {
            return new static(array_pop($this->_internal));
        }

        $results = [];
        $length = $this->length;

        foreach (range(1, min($count, $length)) as $item) {
            $results[] = array_pop($this->_internal);
        }

        return new static($results);
    }

    /**
     *
     * Create a new static with the keys of this static for it's content
     *
     * @return static
     *
     */
    public function keys(): static
    {
        $keys = array_keys($this->_internal);
        return new static($keys);
    }

    /**
     *
     * Get element at index
     *
     * @param int $index
     * @return mixed
     *
     */
    public function at(int $index = 0): mixed
    {
        return $this->_internal[$index] ?? null;
    }

    /**
     *
     * Alias for at method
     *
     * @param int $index
     * @return mixed
     *
     */
    public function idx(int $index = 0): mixed
    {
        return $this->at($index);
    }

    /**
     *
     * Alias for at method
     *
     * @param int $index
     * @return mixed
     *
     */
    public function nth(int $index = 0): mixed
    {
        return $this->at($index);
    }

    /**
     *
     * Run a function on every item in the static
     *
     * @param callable $callback
     * @return static
     *
     */
    public function map(callable $callback): static
    {
        $result = array_map($callback, $this->_internal);
        return new static($result);
    }

    /**
     *
     * Run a filter callback on every item in the static
     *
     * @param callable $callback
     * @return static
     *
     */
    public function filter(callable $callback): static
    {
        $filtered = array_filter($this->_internal, $callback, ARRAY_FILTER_USE_BOTH);
        return new static($filtered);
    }

    /**
     *
     * Split static in chunks
     *
     * @param int $size
     * @param bool $preserveKeys
     * @return static
     *
     */
    public function chunks(int $size, bool $preserveKeys = true): static
    {
        $chunks = array_chunk($this->_internal, $size, $preserveKeys);
        $static = new static([]);

        foreach ($chunks as $chunk) {
            $static->push(new static($chunk));
        }

        return $static;
    }

    /**
     *
     * Shuffle a static
     *
     * @return $this
     *
     */
    public function shuffle(): static
    {
        shuffle($this->_internal);
        return $this;
    }

    /**
     *
     * Check if static contains given value
     *
     * @param mixed $value
     * @return bool
     *
     */
    public function contains(mixed $value): bool
    {
        $index = array_search($value, $this->_internal, true);
        return $index !== '';
    }

    /**
     *
     * Remove duplicates from the static (only for core types)
     *
     * @param int $mode
     * @return static
     */
    public function dedup(int $mode = SORT_REGULAR): static
    {
        $arr = array_unique($this->_internal, $mode);
        return new static($arr);
    }

    /**
     *
     * Run each loop on the static
     *
     * @param callable $callback
     * @return void
     *
     */
    public function each(callable $callback): void
    {
        foreach ($this->_internal as $key => $value) {
            $callback($key, $value);
        }
    }

    /**
     *
     * Find value in the static
     *
     * @param callable $callback
     * @return mixed
     *
     */
    public function find(callable $callback): mixed
    {
        $output = null;

        foreach ($this->_internal as $key => $value) {
            $result = $callback($key, $value);

            if ($result) {
                $output = $value;
            }
        }

        return $output;
    }

    /**
     *
     * Find index of the value (should really only be used for 0 based arrays)
     *
     * @param callable $callback
     * @return int
     *
     */
    public function findIndex(callable $callback): int
    {
        $output = -1;

        foreach ($this->_internal as $key => $value) {
            $result = $callback($key, $value);

            if ($result) {
                $output = $key;
            }
        }

        return $output;
    }

    /**
     *
     * Reduce the static to a single value
     *
     * @param callable $callback
     * @return int
     *
     */
    public function reduce(callable $callback): int
    {
        return array_reduce($this->_internal, $callback);
    }

    /**
     *
     * Basic value sorting with options
     *
     * @param int $sort
     * @param int $flag
     * @param bool $maintain
     * @return $this
     *
     */
    public function sort(int $sort = Sorting::ASC, int $flag = SORT_REGULAR, bool $maintain = false): static
    {
        if (!$maintain) {
            if ($sort === Sorting::ASC) {
                sort($this->_internal, $flag);
            } else {
                rsort($this->_internal, $flag);
            }
        } elseif ($sort === Sorting::ASC) {
            asort($this->_internal, $flag);
        } else {
            arsort($this->_internal, $flag);
        }

        return $this;
    }

    /**
     *
     * Sort by given object key. Only works on collection of objects where key resides.
     *
     * @param string $key
     * @return $this
     *
     */
    public function sortBy(string $key): static
    {
        usort($this->_internal, static function ($a, $b) use ($key)
        {
            if (is_string($a->{$key})) {
                return strcasecmp(Text::deburr($a->{$key}), Text::deburr($b->{$key}));
            }

            if (is_numeric($a->{$key})) {
                if ($a->{$key} > $b->{$key}) {
                    return 1;
                }
                if ($a->{$key} < $b->{$key}) {
                    return -1;
                }

                return 0;
            }

            if (is_bool($a->{$key})) {
                if ($a->{$key} && !$b->{$key}) {
                    return 1;
                }

                if (!$a->{$key} && $b->{$key}) {
                    return -1;
                }

                return 0;
            }

            return 0;
        });

        return new static($this->_internal);
    }

    /**
     *
     * A Nice way to traverse static and contained array/objects
     *
     * @param string $dotNotation
     * @return mixed
     *
     */
    public function get(string $dotNotation): mixed
    {
        $parts = explode('.', $dotNotation);
        $value = $this->_internal;

        foreach ($parts as $num => $part) {
            if ($value instanceof static) {
                $slice = array_slice($parts, $num, count($parts), false);
                $value = $value->get(implode('.', $slice));
            } elseif (is_object($value)) {
                $value = $value->{$part} ?? null;
            } elseif (is_array($value)) {
                $value = $value[$part] ?? null;
            }
        }

        return $value;
    }

    /**
     *
     * Get the differences between 2 collections
     * Note that the returned differences come from the first collection
     *
     * @param Collection|array $collection
     * @param bool $assoc
     * @return $this
     *
     */
    public function diff(Collection|array $collection, bool $assoc = false): static
    {
        if ($collection instanceof Collection) {
            $collection = $collection->unwrap();
        }

        if ($assoc) {
            $diff = array_diff_assoc($this->_internal, $collection);
            return new static($diff);
        }

        $diff = array_diff($this->_internal, $collection);
        return new static($diff);
    }

    /**
     *
     * Return a collection with all the values that intersect between this collection and the
     * provided one
     *
     * @param Collection|array $collection
     * @param bool $assoc
     * @return $this
     *
     */
    public function intersect(Collection|array $collection, bool $assoc = false): static
    {
        if ($collection instanceof Collection) {
            $collection = $collection->unwrap();
        }

        if ($assoc) {
            $diff = array_intersect_assoc($this->_internal, $collection);
            return new static($diff);
        }

        $diff = array_intersect($this->_internal, $collection);
        return new static($diff);
    }

    /**
     *
     * Merge this collection with another recursively or not.
     *
     * Recursive only works on associative arrays/collections
     *
     * @param Collection|array $collection
     * @param bool $recursive
     * @return $this
     *
     */
    public function merge(Collection|array $collection, bool $recursive = false): Collection
    {
        if ($collection instanceof Collection) {
            $collection = $collection->unwrap();
        }

        if ($recursive) {
            $arr = array_merge_recursive($this->_internal, $collection);
        } else {
            $arr = array_merge($this->_internal, $collection);
        }

        return new static($arr);
    }

    /**
     *
     * Pull a value out of the collection and return it
     *
     * @param int|string $index
     * @param bool $keepIndexes
     * @return mixed
     *
     */
    public function pull(int|string $index, bool $keepIndexes = false): mixed
    {
        if (isset($this->_internal[$index])) {
            $var = $this->_internal[$index];
            unset($this->_internal[$index]);

            if ($keepIndexes) {
                $this->_internal = array_values($this->_internal);
            }

            return $var;
        }

        return null;
    }

    // ----------------------------------------- Interface Implementations ----------------------------------------- //

    /**
     *
     * JSON encoding support
     *
     * @return string
     *
     * @throws \JsonException
     */
    public function jsonSerialize(): string
    {
        return json_encode($this->unwrap(), JSON_THROW_ON_ERROR);
    }

    /**
     *
     * Get current element of the array
     *
     * @return mixed
     *
     */
    public function current(): mixed
    {
        return current($this->_internal);
    }

    /**
     *
     * Get next element in the array
     *
     * @return void
     *
     */
    public function next(): void
    {
        next($this->_internal);
    }

    /**
     *
     * Get the current key
     *
     * @return mixed
     *
     */
    public function key(): mixed
    {
        return key($this->_internal);
    }

    /**
     *
     * Is the current index valid in the array
     *
     * @return bool
     *
     */
    public function valid(): bool
    {
        $key = key($this->_internal);
        return (isset($this->_internal[$key]));
    }

    /**
     *
     * Rewind the array to first element
     *
     * @return void
     *
     */
    public function rewind(): void
    {
        reset($this->_internal);
    }
}