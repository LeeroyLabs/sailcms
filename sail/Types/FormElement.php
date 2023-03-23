<?php

namespace SailCMS\Types;

use SailCMS\Contracts\Castable;

class FormElement implements Castable
{
    public function __construct(
        public readonly string $type = 'text',
        public readonly string $name = '',
        public readonly LocaleField|null $label = null,
        public readonly bool $required = false
    ) {
    }

    /**
     *
     * Cast to simpler type
     *
     * @return array
     *
     */
    public function castFrom(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'label' => $this->label->castFrom(),
            'required' => $this->required
        ];
    }

    /**
     *
     * Cast to FormElement
     *
     * @param  mixed  $value
     * @return mixed
     *
     */
    public function castTo(mixed $value): mixed
    {
        return new self($value->type, $value->name, new LocaleField((array)$value->label), $value->required);
    }
}