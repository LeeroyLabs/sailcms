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
     * @param $update
     * @return void
     * @throws DatabaseException
     *
     */
    public function runUpdate($update): void
    {
        $this->updateMany([], $update);
    }

    /**
     *
     * Run insert code
     *
     * @param $record
     * @return void
     * @throws DatabaseException
     *
     */
    public function runInsert($record): void
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

    // unused
    public function fields(bool $fetchAllFields = false): array
    {
        return [];
    }
}