<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Contracts\DatabaseType;

class Input implements DatabaseType
{
    const INPUT_TYPE_CHECKBOX = 'checkbox';
    const INPUT_TYPE_NUMBER = 'number';

    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?Collection $choices = new Collection([])
    ) {
    }

    public function toDBObject(): \stdClass|array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'choices' => $this->choices
        ];
    }
}