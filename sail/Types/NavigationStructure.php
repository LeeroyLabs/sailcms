<?php

namespace SailCMS\Types;

use SailCMS\Contracts\Castable;

// TODO: Implement

class NavigationStructure implements Castable
{
    /**
     * @return mixed
     */
    public function castFrom(): mixed
    {
        return [];
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    public function castTo(mixed $value): mixed
    {
        return [];
    }
}