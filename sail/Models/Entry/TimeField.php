<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\Fields\InputTimeField;
use SailCMS\Types\StoringType;

class TimeField extends Field
{
    const TIME_FORMAT_DEFAULT = 'H:i';

    public function description(): string
    {
        return 'Field to implement an time html input.';
    }

    public function storingType(): string
    {
        return StoringType::STRING->value;
    }

    public function defaultSettings(): Collection
    {
        return new Collection([
            InputTimeField::defaultSettings()
        ]);
    }

    protected function defineBaseConfigs(): void
    {
        $this->baseConfigs = new Collection([
            InputTimeField::class
        ]);
    }

    protected function validate(mixed $content): ?Collection
    {
        // Nothing to implement
        return null;
    }
}