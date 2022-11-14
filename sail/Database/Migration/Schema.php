<?php

namespace SailCMS\Database\Migration;

use SailCMS\Errors\DatabaseException;

class Schema
{
    private Executor $executor;

    public function __construct(string $collection)
    {
        $this->executor = new Executor($collection);
    }

    /**
     *
     * Rename a field to new name (on all records in collection)
     *
     * @param  string  $collection
     * @param  string  $field
     * @param  string  $newName
     * @return void
     * @throws DatabaseException
     *
     */
    public static function rename(string $collection, string $field, string $newName): void
    {
        $instance = new static($collection);
        $instance->executor->runUpdate(['$rename' => [$field => $newName]]);
    }

    /**
     *
     * Add a field and a base value for it
     *
     * @param  string  $collection
     * @param  string  $field
     * @param  mixed   $baseValue
     * @return void
     * @throws DatabaseException
     *
     */
    public static function add(string $collection, string $field, mixed $baseValue): void
    {
        $instance = new static($collection);
        $instance->executor->runUpdate(['$set' => [$field => $baseValue]]);
    }

    /**
     *
     * Remove a field from the collection
     *
     * @param  string  $collection
     * @param  string  $field
     * @return void
     * @throws DatabaseException
     *
     */
    public static function remove(string $collection, string $field): void
    {
        $instance = new static($collection);
        $instance->executor->runUpdate(['$unset' => $field]);
    }

    /**
     *
     * Add Indexes
     *
     * @param  string  $collection
     * @param  array   $index
     * @return void
     * @throws DatabaseException
     *
     */
    public static function index(string $collection, array $index): void
    {
        $instance = new static($collection);
        $instance->executor->runIndex($index);
    }

    /**
     *
     * Drop indexes
     *
     * @param  string  $collection
     * @param  array   $index
     * @return void
     * @throws DatabaseException
     *
     */
    public static function dropIndex(string $collection, array $index): void
    {
        $instance = new static($collection);
        $instance->executor->runDropIndex($index);
    }

    /**
     *
     * Insert a record
     *
     * @param  string  $collection
     * @param  array   $record
     * @return void
     * @throws DatabaseException
     *
     */
    public static function addRecord(string $collection, array $record): void
    {
        $instance = new static($collection);
        $instance->executor->runInsert($record);
    }

    /**
     *
     * Remove a record
     *
     * @param  string  $collection
     * @param  array   $query
     * @return void
     * @throws DatabaseException
     *
     */
    public static function removeRecord(string $collection, array $query): void
    {
        $instance = new static($collection);
        $instance->executor->runDelete($query);
    }
}