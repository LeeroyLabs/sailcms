<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\Fields\InputTextField;
use SailCMS\Types\Fields\InputUrlField;
use SailCMS\Types\StoringType;

class UrlField extends Field
{
    public const SEARCHABLE = true;


    public function description(): string
    {
        return 'Field to implement an url input';
    }

    public function storingType(): string
    {
        return StoringType::STRING->value;
    }

    public function defaultSettings(): Collection
    {
        return new Collection([
            InputUrlField::defaultSettings()
        ]);
    }

    protected function defineBaseConfigs(): void
    {
        $this->baseConfigs = new Collection([
            InputTextField::class
        ]);
    }

    protected function validate(Collection $content): ?Collection
    {
        // Nothing to implement
        return null;
    }
}