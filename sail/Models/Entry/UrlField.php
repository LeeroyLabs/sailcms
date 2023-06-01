<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\FieldCategory;
use SailCMS\Types\Fields\InputUrlField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class UrlField extends Field
{
    public const SEARCHABLE = true;

    public function description(): LocaleField
    {
        return new LocaleField([
            'en' => 'Allows a URL and validates it.',
            'fr' => 'Permet une URL et la valide.'
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
        return FieldCategory::TEXT->value;
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
            InputUrlField::class
        ]);
    }

    protected function validate(mixed $content): ?Collection
    {
        // Nothing to implement
        return null;
    }
}