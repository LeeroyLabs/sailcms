<?php

namespace SailCMS\Types;

use SailCMS\Contracts\DatabaseType;

class Username implements DatabaseType
{
    public function __construct(public readonly string $first, public readonly string $last, public readonly string $full)
    {
    }

    public function toDBObject(): \stdClass|array
    {
        return [
            'first' => $this->first,
            'last' => $this->last,
            'full' => $this->full
        ];
    }
}