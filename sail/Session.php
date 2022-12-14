<?php

namespace SailCMS;

use MongoDB\BSON\ObjectId;
use SailCMS\Contracts\AppSession;
use SailCMS\Session\Stateless;

final class Session
{
    private static AppSession $adapter;
    private static string $adapterType;

    private function __construct()
    {
    }

    /**
     *
     * Initialize the session
     *
     * @return Session
     *
     */
    public static function manager(): Session
    {
        if (!isset(self::$adapter)) {
            $adapter = setting('session.mode', Stateless::class);

            self::$adapter = new $adapter();
            self::$adapterType = strtolower(self::$adapter->type());
        }

        return new static();
    }

    /**
     *
     * Set a key/value pair for Standard session
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     *
     */
    public function set(string $key, mixed $value): void
    {
        if (self::$adapterType !== 'stateless') {
            self::$adapter->set($key, $value);
        }
    }

    /**
     *
     * Get the value for given key
     *
     * @param  string  $key
     * @return mixed
     *
     */
    public function get(string $key): mixed
    {
        return self::$adapter->get($key);
    }

    /**
     *
     * Get the id key from the session
     *
     * @return string
     *
     */
    public function getId(): string
    {
        return self::$adapter->getId();
    }

    /**
     *
     * Remove key from session
     *
     * @param  string  $key
     * @return void
     *
     */
    public function remove(string $key): void
    {
        self::$adapter->remove($key);
    }

    /**
     *
     * Get all key/values
     *
     * @return Collection
     *
     */
    public function all(): Collection
    {
        return self::$adapter->all();
    }

    /**
     *
     * Clear the whole session
     *
     * @return void
     *
     */
    public function clear(): void
    {
        self::$adapter->clear();
    }

    /**
     *
     * Set the user id for the JWT adapter
     *
     * @param  ObjectId|string  $id
     * @return void
     *
     */
    public function setUserId(ObjectId|string $id): void
    {
        if (self::$adapterType === 'stateless') {
            if (!is_string($id)) {
                $id = (string)$id;
            }

            self::$adapter->set('user_id', $id);
        }
    }

    /**
     *
     * Get type of session
     *
     * @return string
     *
     */
    public function type(): string
    {
        return self::$adapterType;
    }
}