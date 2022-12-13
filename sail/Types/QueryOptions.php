<?php

namespace SailCMS\Types;

class QueryOptions
{
    public int $skip = 0;
    public int $limit = 10_000;
    public array|null $sort = null;
    public array|null $projection = null;
    public string $collation = '';

    /**
     *
     * Query Options
     *
     * @param  array|null  $projection
     * @param  int         $skip
     * @param  int         $limit
     * @param  array|null  $sort
     * @param  string      $collation
     * @return QueryOptions
     *
     */
    public static function init(array|null $projection = null, int $skip = 0, int $limit = 10_000, array|null $sort = null, string $collation = ''): QueryOptions
    {
        $options = new static();
        $options->skip = $skip;
        $options->limit = $limit;
        $options->sort = $sort;
        $options->projection = $projection;
        $options->collation = $collation;

        return $options;
    }

    /**
     *
     * Options with only Sort
     *
     * @param  array  $sort
     * @return QueryOptions
     *
     */
    public static function initWithSort(array $sort): QueryOptions
    {
        $options = new static();
        $options->skip = 0;
        $options->limit = 10_000;
        $options->sort = $sort;
        $options->projection = null;
        $options->collation = '';

        return $options;
    }

    /**
     *
     * Options with only Projection
     *
     * @param  array  $projection
     * @return QueryOptions
     *
     */
    public static function initWithProjection(array $projection): QueryOptions
    {
        $options = new static();
        $options->skip = 0;
        $options->limit = 10_000;
        $options->sort = null;
        $options->projection = $projection;
        $options->collation = '';

        return $options;
    }

    public static function initWithCollation(string $collation): QueryOptions
    {
        $options = new static();
        $options->skip = 0;
        $options->limit = 10_000;
        $options->sort = null;
        $options->projection = null;
        $options->collation = $collation;

        return $options;
    }

    /**
     *
     * Options with only Skip and Limit
     *
     * @param  int  $skip
     * @param  int  $limit
     * @return QueryOptions
     *
     */
    public static function initWithPagination(int $skip = 0, int $limit = 1_000): QueryOptions
    {
        $options = new static();
        $options->skip = $skip;
        $options->limit = $limit;
        $options->sort = null;
        $options->projection = null;
        $options->collation = '';

        return $options;
    }
}