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

    public stdClass $flags;
    public array $customMeta;

    private static array $registered = [
        'Flags' => [
            'type' => UserMeta::TYPE_CUSTOM,
            'callback' => [UserMeta::class, 'getAvailableFlags']
        ]
    ];

    private static array $registeredFlags = ['use2fa'];

    public function __construct(object $object)
    {
        if (get_class($object) === Collection::class) {
            $object = $object->unwrap();
        }

        $userMeta = [];

        foreach ($object as $key => $value) {
            if ($key === 'flags') {
                $this->flags = $value;
            } else {
                $userMeta[$key] = $value ?? '';
            }
        }

        $this->customMeta = $userMeta;
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

        $output->flags = $this->flags;

        foreach ($this->customMeta as $key => $value) {
            $output->{$key} = $value ?? '';
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
        if ($name === 'flags') {
            return $this->flags ?? null;
        }

        return $this->customMeta[$name] ?? '';
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
        if ($name === 'flags') {
            $this->flags = $value;
            return;
        }

        if (is_array($value)) {
            $this->customMeta[$name] = new Collection($value);
            return;
        }

        $this->customMeta[$name] = $value;
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
        if (self::$registered[$name]) {
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
        self::$registered[$key] = ['type' => $type, 'callback' => $callback];
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
        self::$registeredFlags[] = $key;
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

        foreach (self::$registered as $key => $options) {
            switch ($options['type']) {
                case self::TYPE_BOOL:
                    $graphql .= $key . ": Boolean\n";
                    break;

                case self::TYPE_FLOAT:
                    $graphql .= $key . ": Float\n";
                    break;

                case self::TYPE_INT:
                    $graphql .= $key . ": Int\n";
                    break;

                default:
                case self::TYPE_STRING:
                    $graphql .= $key . ": String\n";
                    break;

                case self::TYPE_CUSTOM:
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

        foreach (self::$registeredFlags as $value) {
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