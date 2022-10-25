<?php

namespace SailCMS\Types;

use SailCMS\Contracts\DatabaseType;

class Username implements DatabaseType
{
    public function __construct(public readonly string $first, public readonly string $last, public readonly string $full)
    {
    }

    /**
     *
     * Return an instance with a regular object
     *
     * @param  object  $name
     * @return $this
     *
     */
    public static function initWith(object $name): Username
    {
        return new static($name->first, $name->last, $name->first . ' ' . $name->last);
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