<?php

namespace SailCMS\Search;

use SailCMS\Types\SearchResults;

interface Adapter
{
    public function store();

    public function search(string $search, array $meta = [], string $dataIndex = ''): SearchResults;

    public function getRawAdapter(): mixed;
}