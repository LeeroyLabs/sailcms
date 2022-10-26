<?php

namespace SailCMS\Types;

use SailCMS\Collection;

class Listing
{
    public function __construct(public readonly Pagination $pagination, public readonly Collection $list) { }

    public static function empty(): Listing
    {
        return new Listing(new Pagination(), new Collection([]));
    }
}