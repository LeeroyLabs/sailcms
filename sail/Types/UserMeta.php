<?php

namespace SailCMS\Types;

use SailCMS\Collection;
use SailCMS\Contracts\DatabaseType;
use SailCMS\GraphQL\Types as GTypes;
use SailCMS\Text;
use stdClass;

class UserMeta implements DatabaseType
{
    public const TYPE_STRING = 1;
    public const TYPE_INT = 2;
    public const TYPE_FLOAT = 3;
    public const TYPE_BOOL = 4;
    public const TYPE_CUSTOM = 5;

    public readonly stdClass $flags;

    private static array $registered = [
        'Flags' => [
            'type' => UserMeta::TYPE_CUSTOM,
            'callback' => [UserMeta::class, 'getAvailableFlags']
        ]
    ];

    private static array $registeredFlags = ['use2fa'];

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
     * @param  bool  $inputs
     * @return string
     *
     */
    public static function getAvailableMeta(bool $inputs = false): string
    {
        $graphql = '';

        foreach (static::$registered as $key => $options) {
            switch ($options['type']) {
                case static::TYPE_BOOL:
                    $graphql .= $key . ": Boolean\n";
                    break;

                case static::TYPE_FLOAT:
                    $graphql .= $key . ": Float\n";
                    break;

                case static::TYPE_INT:
                    $graphql .= $key . ": Int\n";
                    break;

                default:
                case static::TYPE_STRING:
                    $graphql .= $key . ": String\n";
                    break;

                case static::TYPE_CUSTOM:
                    $input = ($inputs) ? 'Input' : '';
                    $graphql .= Text::snakeCase($key) . ": {$key}{$input}\n";
                    break;
            }
        }

        return $graphql;
    }

    /**
     *
     * Get all available flag registered in the system
     *
     * @return string
     *
     */
    public static function getAvailableFlags(): string
    {
        $graphql = '';

        foreach (static::$registeredFlags as $value) {
            $graphql .= $value . ": Boolean\n";
        }

        return $graphql;
    }

    /**
     *
     * Return a simple format for the database
     *
     * @return stdClass|array
     *
     */
    public function toDBObject(): \stdClass|array
    {
        return $this->simplify();
    }
}