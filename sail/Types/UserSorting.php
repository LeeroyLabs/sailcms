<?php

namespace SailCMS\types;

class UserSorting
{
    public readonly string $sort;
    public readonly int $order;

    public function __construct(string $sort, string $order)
    {
        $this->sort = $sort;

        if (strtolower($order) === 'asc' || strtolower($order) === 'ascending') {
            $this->order = 1;
            return;
        }

        $this->order = -1;
    }
}