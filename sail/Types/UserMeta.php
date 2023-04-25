<?php

namespace SailCMS\Types;

use SailCMS\Collection;
use SailCMS\Contracts\Castable;
use SailCMS\Text;
use stdClass;

class UserMeta implements Castable, \JsonSerializable
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

    public function __construct(object $object = null)
    {
        if (!$object) {
            return;
        }

        if (get_class($object) === Collection::class) {
            $object = $object->unwrap();
        }

        $userMeta = [];

        foreach ($object as $key => $value) {
            if ($key === 'flags') {
                $this->flags = (object)$value;
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

        if (!isset($this->flags)) {
            $this->flags = new stdClass();

            foreach (self::$registeredFlags as $flag) {
                $this->flags->{$flag} = false;
            }
        }

        $output->flags = $this->flags;

        if (isset($this->customMeta)) {
            foreach ($this->customMeta as $key => $value) {
                $output->{$key} = $value ?? '';
            }
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
     * @param  mixed   $value
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        if ($name === 'flags') {
            $this->flags = (object)$value;
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
                    $graphql .= Text::from($key)->snake()->concat("{$key}{$input}\n", ': ')->value();
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
     * Cast to simpler format from UserMeta
     *
     * @return array
     *
     */
    public function castFrom(): array
    {
        $fields = self::$registered;
        $fieldSet = [];

        foreach ($fields as $key => $settings) {
            $type = $settings['type'];

            $defaultValue = match ($type) {
                self::TYPE_STRING => '',
                self::TYPE_INT, self::TYPE_FLOAT => 0,
                self::TYPE_BOOL => false,
                default => null,
            };

            $fieldSet[$key] = $this->customMeta[$key] ?? $defaultValue;
        }

        return [
            'flags' => $this->flags,
            ...$fieldSet
        ];
    }

    /**
     *
     * Cast to UserMeta
     *
     * @param  mixed  $value
     * @return UserMeta
     *
     */
    public function castTo(mixed $value): UserMeta
    {
        if (is_array($value)) {
            $value = (object)$value;
        }

        $instance = new self();

        foreach ($value as $key => $v) {
            $instance->{$key} = $v;
        }

        return $instance;
    }

    /**
     *
     * Automatically simplify when you serialize
     *
     * @return array
     *
     */
    public function jsonSerialize(): array
    {
        return $this->castFrom();
    }
}