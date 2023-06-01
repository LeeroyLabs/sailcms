<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\FieldCategory;
use SailCMS\Types\Fields\InputTimeField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class TimeField extends Field
{
    public const TIME_FORMAT_DEFAULT = 'H:i';

    public function description(): LocaleField
    {
        return new LocaleField([
            'en' => 'Allows time selection.',
            'fr' => 'Permet une sélection d\'heure'
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
        return FieldCategory::DATETIME->value;
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