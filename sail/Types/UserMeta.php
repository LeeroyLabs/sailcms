<?php

namespace SailCMS\Types;

use SailCMS\Collection;
use SailCMS\GraphQL\Types as GTypes;
use stdClass;

class UserMeta
{
    public const TYPE_STRING = 1;
    public const TYPE_INT = 2;
    public const TYPE_FLOAT = 3;
    public const TYPE_BOOL = 4;
    public const TYPE_CUSTOM = 5;

    public readonly stdClass $flags;

    private static array $registered = [
        'flags' => [
            'type' => UserMeta::TYPE_CUSTOM,
            'callback' => [UserMeta::class, 'getAvailableFlags']
        ]
    ];

    private static array $registeredFlags = ['cubeler'];

    public function __construct(object $object)
    {
        foreach ($object as $key => $value) {
            if (is_array($value)) {
                $this->{$key} = new Collection($value);
            } else {
                $this->{$key} = $value;
            }
        }
    }

    /**
     *
     * Simplify the object to basic php for database insertion
     *
     * @return stdClass
     *
     */
    public function simplify(): stdClass
    {
        $output = new stdClass();

        foreach ($this as $key => $value) {
            $output->{$key} = $value;
        }

        return $output;
    }

    /**
     *
     * Get a dynamic property
     *
     * @param  string  $name
     * @return mixed
     *
     */
    public function __get(string $name): mixed
    {
        return $this->{$name} ?? null;
    }

    /**
     *
     * Set a dynamic property
     *
     * @param  string  $name
     * @param          $value
     * @return void
     */
    public function __set(string $name, $value): void
    {
        if (is_array($value)) {
            $this->{$name} = new Collection($value);
        } else {
            $this->{$name} = $value;
        }
    }

    /**
     *
     * Check if property exists with any type of value
     *
     * @param  string  $name
     * @return bool
     *
     */
    public function __isset(string $name): bool
    {
        if (static::$registered[$name]) {
            return (isset($this->{$name}));
        }

        return false;
    }

    /**
     *
     * Register an available meta
     *
     * @param  string         $key
     * @param  int            $type
     * @param  callable|null  $callback
     * @return void
     *
     */
    public static function register(string $key, int $type = UserMeta::TYPE_STRING, callable $callback = null): void
    {
        static::$registered[$key] = ['type' => $type, 'callback' => $callback];
    }

    /**
     *
     * Register a flag
     *
     * @param  string  $key
     * @return void
     *
     */
    public static function registerFlag(string $key): void
    {
        static::$registeredFlags[] = $key;
    }

    /**
     *
     * Get all available meta registered in the system
     *
     * @return Collection
     *
     */
    public static function getAvailableMeta(): Collection
    {
        return new Collection(static::$registered);
    }

    /**
     *
     * Get all available flag registered in the system
     *
     * @return Collection
     *
     */
    public static function getAvailableFlags(): Collection
    {
        $list = [];

        foreach (static::$registeredFlags as $value) {
            $list[$value] = GTypes::boolean();
        }

        return new Collection($list);
    }
}