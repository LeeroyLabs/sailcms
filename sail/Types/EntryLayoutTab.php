<?php

namespace SailCMS\Types;

use SailCMS\Contracts\Castable;

class EntryLayoutTab implements Castable
{
    public function __construct(
        public string $label = '',
        public array  $fields = []
    )
    {
    }

    public function castFrom(): mixed
    {
        return [
            'label' => $this->label,
            'fields' => $this->fields
        ];
    }

    public function castTo(mixed $value): mixed
    {
        if (is_array($value)) {
            $value = (object)$value;
        }

        return new self($value->label, $value->fields);
    }
}