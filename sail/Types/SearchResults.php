<?php

namespace SailCMS\Types;

use \SailCMS\Collection;

class SearchResults
{
    public int $count = 0;
    public Collection $results;

    public function __construct(array $results, int $count = 0)
    {
        $this->count = $count;
        $this->results = new Collection($results);
    }
}