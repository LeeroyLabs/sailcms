<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\Fields\InputSelectField;
use SailCMS\Types\StoringType;

class SelectField extends Field
{
    public const SEARCHABLE = false;

    public function description(): string
    {
        return "Field to add a select";
    }

    public function storingType(): string
    {
        return StoringType::STRING->name;
    }

    public function defaultSettings(): Collection
    {
        return new Collection([
            InputSelectField::defaultSettings()
        ]);
    }

    protected function defineBaseConfigs(): void
    {
        $this->baseConfigs = new Collection([
            InputSelectField::class
        ]);
    }

    /**
     *
     * Select validation
     *
     * @param  mixed  $content
     * @return Collection|null
     *
     */
    protected function validate(mixed $content): ?Collection
    {
        return null;
    }
}