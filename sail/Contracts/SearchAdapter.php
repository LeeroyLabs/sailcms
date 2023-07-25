<?php

namespace SailCMS\Contracts;

use SailCMS\Types\SearchResults;

interface SearchAdapter
{
    public function store(array|object $document, string $dataIndex = '');

    public function remove(string $id, string $dataIndex = '');

    public function search(string $search, array $meta = [], string $dataIndex = ''): SearchResults;

    public function getRawAdapter(): mixed;
}