<?php

namespace SailCMS\Types;

class Pagination
{
    public function __construct(public readonly int $currentPage = 1, public readonly int $pageCount = 1, public readonly int $total = 0) { }
}