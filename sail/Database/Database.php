<?php

namespace SailCMS\Database;

use SailCMS\Errors\DatabaseException;
use MongoDB\Client;

class Database
{
    private static ?Client $client = null;

    /**
     *
     * Connect to database and create a persistent connection
     *
     * @throws DatabaseException
     *
     */
    private static function init(): void
    {
        try {
            $options = [];
            $extra = '';

            if ($_ENV['DATABASE_DSN'] !== '') {
                self::$client = new Client($_ENV['DATABASE_DSN'], []);
            } else {
                if ($_ENV['DATABASE_USER']) {
                    $auth = "{$_ENV['DATABASE_USER']}:{$_ENV['DATABASE_PASSWORD']}@";
                    $extra = '?serverSelectionTimeoutMS=5000&connectTimeoutMS=10000&authSource=' . $_ENV['DATABASE_AUTH_DB'] . '&authMechanism=SCRAM-SHA-256';

                    self::$client = new Client("mongodb://{$auth}{$_ENV['DATABASE_HOST']}:{$_ENV['DATABASE_PORT']}/{$_ENV['DATABASE_DB']}{$extra}", $options);
                } else {
                    self::$client = new Client("mongodb://{$_ENV['DATABASE_HOST']}:{$_ENV['DATABASE_PORT']}/{$_ENV['DATABASE_DB']}", $options);
                }
            }
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 500);
        }
    }

    /**
     *
     * Fetch the active instance (if any) or create one
     *
     * @return Client|null
     * @throws DatabaseException
     *
     */
    public static function instance(): ?Client
    {
        if (!self::$client) {
            self::init();
        }

        return self::$client;
    }

    /**
     *
     * Disconnect and destroy persistent connection
     *
     */
    public static function disconnect(): void
    {
        self::$client = null;
    }
}