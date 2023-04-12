<?php

namespace SailCMS\Types;

class Pagination
{
    public function __construct(public readonly int $current = 1, public readonly int $totalPages = 1, public readonly int $total = 0) { }
}