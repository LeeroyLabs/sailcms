<?php

namespace SailCMS\Search;

use SailCMS\Types\SearchResults;

class Database implements Adapter
{

    public function store(object|array $document, string $dataIndex = '')
    {
        // TODO: Implement store() method.
    }

    public function search(string $search, array $meta = [], string $dataIndex = ''): SearchResults
    {
        // TODO: Implement search() method.
        return new SearchResults([], 0);
    }

    public function getRawAdapter(): mixed
    {
        // TODO: Implement getRawAdapter() method.
        return null;
    }
}