<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Contracts\DatabaseType;
use SailCMS\Types\LocaleField;

abstract class Field implements DatabaseType
{
    public function __construct(
        public readonly LocaleField $label,
        public readonly bool $required = false
    ) {
    }

    /**
     * @return array
     */
    public function toDBObject(): array
    {
        return [
            'label' => $this->label->toDBObject(),
            'required' => $this->required
        ];
    }

    abstract public function validate(mixed $content): Collection;
}