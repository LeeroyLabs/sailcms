<?php

namespace SailCMS\Types;

class QueryOptions
{
    public int $skip = 0;
    public int $limit = 10_000;
    public array|null $sort = null;
    public array|null $projection = null;

    public static function init(array|null $projection = null, int $skip = 0, int $limit = 10_000, array|null $sort = null): QueryOptions
    {
        $options = new self();
        $options->skip = $skip;
        $options->limit = $limit;
        $options->sort = $sort;
        $options->projection = $projection;

        return $options;
    }
}