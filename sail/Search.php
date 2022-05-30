<?php

namespace SailCMS;

use SailCMS\Search\Adapter;
use SailCMS\Search\Database;
use SailCMS\Types\SearchResults;

class Search
{
    private static Adapter $adapter;
    private static array $registeredAdapters = [];

    public function __construct()
    {
        $engine = $_ENV['SEARCH_ENGINE'] ?? 'database';

        if (empty(static::$adapter)) {
            if (!empty(static::$registeredAdapters[$engine])) {
                $adapterInfo = static::$registeredAdapters[$engine];
                static::$adapter = new $adapterInfo();
                return;
            }

            static::$adapter = new Database();
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
     * @param string $search
     * @param array $meta
     * @param string $dataIndex
     * @return SearchResults
     *
     */
    public function search(string $search, array $meta = [], string $dataIndex = ''): SearchResults
    {
        return static::$adapter->search($search, $meta, $dataIndex);
    }

    /**
     *
     * Try to execute the give method on the adapter. if not available, null is returned
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     *
     */
    public function execute(string $method, array $arguments = []): mixed
    {
        if (method_exists(static::$adapter, $method)) {
            return call_user_func_array([static::$adapter, $method], $arguments);
        }

        return null;
    }

    /**
     *
     * Register a search adapter
     *
     * @param string $name
     * @param string $className
     * @return void
     *
     */
    public static function registerAdapter(string $name, string $className): void
    {
        static::$registeredAdapters[$name] = $className;
    }

    /**
     *
     * Register system provided adapters
     *
     * @return void
     *
     */
    public static function registerSystemAdapters(): void
    {
        static::registerAdapter('meili', Meili::class);
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
        return static::$adapter->getRawAdapter();
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
        return static::$adapter;
    }
}