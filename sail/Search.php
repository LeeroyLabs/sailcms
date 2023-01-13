<?php

namespace SailCMS;

use SailCMS\Errors\DatabaseException;
use SailCMS\Search\Adapter;
use SailCMS\Search\Database;
use SailCMS\Types\SearchResults;

final class Search
{
    private static Adapter $adapter;
    private static array $registeredAdapters = [];

    public function __construct()
    {
        $engine = env('search_engine', 'database');

        if (empty(self::$adapter)) {
            if (!empty(self::$registeredAdapters[$engine])) {
                $adapterInfo = self::$registeredAdapters[$engine];
                self::$adapter = new $adapterInfo();
                return;
            }

            self::$adapter = new Database();
        }
    }

    /**
     *
     * Initialize the search class for the first time (get adapter up and running)
     *
     * @return void
     *
     */
    public static function init(): void
    {
        new static();
    }

    /**
     *
     * Launch search
     *
     * @param  string  $search
     * @param  array   $meta
     * @param  string  $dataIndex
     * @return SearchResults
     * @throws DatabaseException
     *
     */
    public function search(string $search, array $meta = [], string $dataIndex = ''): SearchResults
    {
        return self::$adapter->search($search, $meta, $dataIndex);
    }

    /**
     *
     * Store a document in the database
     *
     * @param  array|object  $document
     * @param  string        $dataIndex
     * @return void
     * @throws DatabaseException
     *
     */
    public function store(array|object $document, string $dataIndex = ''): void
    {
        self::$adapter->store($document, $dataIndex);
    }

    /**
     *
     * Remove document from search
     *
     * @param  string  $id
     * @param  string  $dataIndex
     * @return void
     * @throws DatabaseException
     *
     */
    public function remove(string $id, string $dataIndex = ''): void
    {
        self::$adapter->remove($id, $dataIndex);
    }

    /**
     *
     * Try to execute the give method on the adapter. if not available, null is returned
     *
     * @param  string  $method
     * @param  array   $arguments
     * @return mixed
     *
     */
    public function execute(string $method, array $arguments = []): mixed
    {
        if (method_exists(self::$adapter, $method)) {
            return call_user_func_array([self::$adapter, $method], $arguments);
        }

        return null;
    }

    /**
     *
     * Register a search adapter
     *
     * @param  string  $name
     * @param  string  $className
     * @return void
     *
     */
    public static function registerAdapter(string $name, string $className): void
    {
        self::$registeredAdapters[$name] = $className;
    }

    /**
     *
     * Register system provided adapters
     *
     * @return void
     *
     * @throws \JsonException
     *
     */
    public static function registerSystemAdapters(): void
    {
        $composerFile = Sail::getWorkingDirectory() . '/composer.json';
        $composer = json_decode(file_get_contents($composerFile), false, 512, JSON_THROW_ON_ERROR);

        $engines = $composer->sailcms->search ?? [];

        foreach ($engines as $name => $engine) {
            self::registerAdapter($name, $engine);
        }
    }

    /**
     *
     * Get the raw client for custom requirements
     *
     * @return mixed
     *
     */
    public static function getRawClient(): mixed
    {
        return self::$adapter->getRawAdapter();
    }

    /**
     *
     * Return the adapter for custom requirements
     *
     * @return Adapter
     *
     */
    public static function getAdapter(): Adapter
    {
        return self::$adapter;
    }
}