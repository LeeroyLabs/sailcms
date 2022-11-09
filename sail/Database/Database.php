<?php

namespace SailCMS\Database;

use MongoDB\Driver\ServerApi;
use SailCMS\Debug;
use SailCMS\Errors\DatabaseException;
use MongoDB\Client;

class Database
{
    private static array $clients = [];

    /**
     *
     * Connect to database and create a unique connection
     *
     * @param  int  $dbIndex
     * @throws DatabaseException
     *
     */
    private static function init(int $dbIndex): void
    {
        try {
            $options = [];
            $extra = '';

            $dsn = ($dbIndex > 0) ? "DATABASE_DSN_{$dbIndex}" : 'DATABASE_DSN';
            Debug::eventStart('Connect to MongoDB');

            if (isset($_ENV[$dsn]) && $_ENV[$dsn] !== '') {
                if (str_starts_with($_ENV[$dsn], 'mongodb+srv')) {
                    $api = new ServerApi(ServerApi::V1);

                    try {
                        self::$clients[$dbIndex] = new Client($_ENV[$dsn], [], ['serverApi' => $api]);
                    } catch (\Exception $e) {
                        print_r($e->getMessage());
                    }
                } else {
                    self::$clients[$dbIndex] = new Client($_ENV[$dsn], []);
                }
            } else {
                throw new DatabaseException("Database DSN is not set for index {$dbIndex}.", 0500);
            }

            Debug::eventEnd('Connect to MongoDB');
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 500);
        }
    }

    /**
     *
     * Fetch the active instance (if any) or create one
     *
     * @param  int  $dbIndex
     * @return Client|null
     * @throws DatabaseException
     *
     */
    public static function instance(int $dbIndex = 0): ?Client
    {
        if (!isset(self::$clients[$dbIndex])) {
            self::init($dbIndex);
        }

        return self::$clients[$dbIndex];
    }
}