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
        $engine = env('search_engine', 'database');

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
     * @param  string  $search
     * @param  array   $meta
     * @param  string  $dataIndex
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
     * @param  string  $method
     * @param  array   $arguments
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
     * @param  string  $name
     * @param  string  $className
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
     * @throws \JsonException
     *
     */
    public static function registerSystemAdapters(): void
    {
        $composerFile = Sail::getWorkingDirectory() . '/composer.json';
        $composer = json_decode(file_get_contents($composerFile), false, 512, JSON_THROW_ON_ERROR);

        $engines = $composer->sailcms->search ?? [];

        foreach ($engines as $name => $engine) {
            static::registerAdapter($name, $engine);
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