<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Contracts\DatabaseType;
use stdClass;

class Input implements DatabaseType
{
    const INPUT_TYPE_CHECKBOX = 'checkbox';
    const INPUT_TYPE_NUMBER = 'number';

    /**
     *
     * Structure to replicate a html input properties
     *
     * @param string $name
     * @param string $type
     * @param Collection|null $choices
     *
     */
    public function __construct(
        public readonly string      $name,
        public readonly string      $type,
        public readonly ?Collection $choices = new Collection([])
    )
    {
    }

    /**
     *
     * Convert to an stdClass object
     *
     * @return stdClass|array
     *
     */
    public function toDBObject(): stdClass|array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'choices' => $this->choices
        ];
    }
}