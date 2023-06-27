<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\FieldCategory;
use SailCMS\Types\Fields\InputSelectField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class SelectField extends Field
{
    public function description(): LocaleField
    {
        return new LocaleField([
            'en' => 'Displays a dropdown selector.',
            'fr' => 'Affiche un menu déroulant'
        ]);
    }

    /**
     *
     * Category of field
     *
     * @return string
     *
     */
    public function category(): string
    {
        return FieldCategory::SELECT->value;
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