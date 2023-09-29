<?php

namespace SailCMS\Database;

use MongoDB\Driver\ServerApi;
use MongoDB\Operation\DatabaseCommand;
use SailCMS\ACL;
use SailCMS\Debug;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use MongoDB\Client;
use SailCMS\Errors\PermissionException;
use SailCMS\Models\User;
use ZipArchive;

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

            if (env($dsn, '') !== '') {
                if (str_starts_with(env($dsn, ''), 'mongodb+srv')) {
                    $api = new ServerApi(ServerApi::V1);
                    self::$clients[$dbIndex] = new Client(env($dsn, ''), [], ['serverApi' => $api]);
                } else {
                    self::$clients[$dbIndex] = new Client(env($dsn, ''));
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

    /**
     *
     * Dump the db
     *
     * @param  string  $databaseName
     * @param  bool    $download
     * @return string
     * @throws DatabaseException
     * @throws PermissionException
     * @throws ACLException
     *
     */
    public function databaseDump(string $databaseName, bool $download = false): string
    {
        if (!User::$currentUser) {
            throw new PermissionException('0403: Permission Denied', 0403);
        }

        if (!ACL::hasPermission(User::$currentUser, ACL::write('Admin'))) {
            throw new PermissionException('0403: Permission Denied', 0403);
        }

        putenv('PATH=/usr/local/bin');
        shell_exec('mongodump --db=' . $databaseName);
        $directoryName = $databaseName . '_' . date('c');

        rename("./dump/" . $databaseName, "./dump/" . $directoryName);
        shell_exec('zip -r ./dump/' . $directoryName . '.zip ./dump/');
        shell_exec('rm -rf ./dump/' . $directoryName);

        return true;
    }
}