<?php

namespace SailCMS\Database\Migration;

use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;

class Executor extends Model
{
    /**
     *
     * Run update code
     *
     * @param  array  $update
     * @return void
     * @throws DatabaseException
     */
    public function runUpdate(array $update): void
    {
        $this->updateMany([], $update);
    }

    /**
     *
     * Run update code
     *
     * @param  string  $field
     * @param  array   $update
     * @return void
     * @throws DatabaseException
     *
     */
    public function runUpdateIfNotSet(string $field, array $update): void
    {
        $this->updateMany([$field => ['$exists' => false]], $update);
    }

    /**
     *
     * Run insert code
     *
     * @param  object|array  $record
     * @return void
     * @throws DatabaseException
     *
     */
    public function runInsert(object|array $record): void
    {
        $this->insert($record);
    }

    /**
     *
     * Run delete code
     *
     * @param $query
     * @return void
     * @throws DatabaseException
     *
     */
    public function runDelete($query): void
    {
        $this->deleteMany($query);
    }

    /**
     *
     * Add indexes for the given list
     *
     * @param  array  $indexes
     * @param  array  $options
     * @return void
     * @throws DatabaseException
     *
     */
    public function runIndex(array $indexes, array $options = []): void
    {
        $this->addIndexes($indexes, $options);
    }

    /**
     *
     * Drop list of indexes
     *
     * @param  array  $indexes
     * @return void
     * @throws DatabaseException
     *
     */
    public function runDropIndex(array $indexes): void
    {
        $this->dropIndexes($indexes);
    }
}