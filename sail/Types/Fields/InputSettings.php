<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Contracts\Castable;

class InputSettings implements Castable
{
    public const INPUT_TYPE_CHECKBOX = 'checkbox';
    public const INPUT_TYPE_NUMBER = 'number';
    public const INPUT_TYPE_REGEX = 'regex';

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
        public readonly string      $name = '',
        public readonly string      $type = '',
        public readonly ?Collection $choices = new Collection([])
    )
    {
    }

    /**
     *
     * Cast to a simpler form from InputSettings
     *
     * @return array
     *
     */
    public function castFrom(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'choices' => $this->choices
        ];
    }

    /**
     *
     * Cast to InputSettings
     *
     * @param mixed $value
     * @return self
     *
     */
    public function castTo(mixed $value): self
    {
        return new self($value->name, $value->type, $value->choices);
    }
}