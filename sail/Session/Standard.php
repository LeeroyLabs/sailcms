<?php

namespace SailCMS\Session;

use Josantonius\Session\Exceptions\HeadersSentException;
use Josantonius\Session\Exceptions\SessionNotStartedException;
use Josantonius\Session\Exceptions\SessionStartedException;
use Josantonius\Session\Exceptions\WrongSessionOptionException;
use Josantonius\Session\Session;
use SailCMS\Collection;
use SailCMS\Contracts\AppSession;

class Standard implements AppSession
{
    private static Session $instance;

    /**
     *
     * Initiate a session
     *
     * @throws HeadersSentException
     * @throws WrongSessionOptionException
     * @throws SessionStartedException
     *
     */
    public function __construct()
    {
        if (isset(static::$instance)) {
            return;
        }

        static::$instance = new Session();
        static::$instance->start([
            'cookie_httponly' => setting('session.httpOnly', true),
            'cookie_lifetime' => setting('session.ttl', 21_600),
            'cookie_samesite' => setting('session.samesite', true),
            'cookie_secure' => true
        ]);
    }

    /**
     *
     * Set a key/value pair
     *
     * @throws SessionNotStartedException
     *
     */
    public function set(string $key, mixed $value): void
    {
        if (static::$instance->isStarted()) {
            static::$instance->set($key, $value);
        }
    }

    /**
     *
     * Get a value from the session
     *
     * @param  string  $key
     * @return mixed
     *
     */
    public function get(string $key): mixed
    {
        if (static::$instance->isStarted() && static::$instance->has($key)) {
            return static::$instance->get($key, null);
        }

        return null;
    }

    /**
     *
     * Remove a key
     *
     * @param  string  $key
     * @return void
     * @throws SessionNotStartedException
     *
     */
    public function remove(string $key): void
    {
        if (static::$instance->isStarted() && static::$instance->has($key)) {
            static::$instance->remove($key);
        }
    }

    /**
     *
     * Get all key/value pairs
     *
     * @return Collection
     *
     */
    public function all(): Collection
    {
        if (static::$instance->isStarted()) {
            return new Collection(static::$instance->all());
        }

        return new Collection([]);
    }

    /**
     *
     * Clear the session
     *
     * @throws SessionNotStartedException
     *
     */
    public function clear(): void
    {
        if (static::$instance->isStarted()) {
            static::$instance->clear();
        }
    }

    /**
     *
     * Get the ID from the token
     *
     * @return string
     *
     */
    public function getId(): string
    {
        if (static::$instance->isStarted()) {
            return static::$instance->getId();
        }

        return '';
    }

    /**
     *
     * Return type of session
     *
     * @return string
     *
     */
    public function type(): string
    {
        return 'standard';
    }
}