<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Contracts\Castable;

class InputSettings implements Castable
{
    public const INPUT_TYPE_CHECKBOX = 'checkbox';
    public const INPUT_TYPE_NUMBER = 'number';

    public const INPUT_TYPE_STRING = 'string';
    public const INPUT_TYPE_OPTIONS = 'options';

    /**
     *
     * Structure to replicate a html input properties
     *
     * @param  string           $name
     * @param  string           $type
     * @param  Collection|null  $options
     *
     */
    public function __construct(
        public readonly string      $name = '',
        public readonly string      $type = '',
        public readonly ?Collection $options = new Collection([])
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
            'options' => $this->options
        ];
    }

    /**
     *
     * Cast to InputSettings
     *
     * @param  mixed  $value
     * @return self
     *
     */
    public function castTo(mixed $value): self
    {
        return new self($value->name, $value->type, $value->options);
    }
}