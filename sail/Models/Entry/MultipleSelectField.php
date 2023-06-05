<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\FieldCategory;
use SailCMS\Types\Fields\InputMultipleSelectField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class MultipleSelectField extends Field
{
    public const SEARCHABLE = false;

    /**
     *
     * Override the constructor to pass a precision to the multiple select
     *
     * @param  LocaleField            $labels
     * @param  array|Collection|null  $settings
     */
    public function __construct(LocaleField $labels, array|Collection|null $settings)
    {
        parent::__construct($labels, $settings);
    }

    public function description(): LocaleField
    {
        return new LocaleField([
            'en' => 'Allows multiple selection within a list.',
            'fr' => 'Permet le choix multiple dans une liste.'
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
        return StoringType::ARRAY->value;
    }

    public function defaultSettings(): Collection
    {
        return new Collection([
            InputMultipleSelectField::defaultSettings()
        ]);
    }

    protected function defineBaseConfigs(): void
    {
        $this->baseConfigs = new Collection([
            InputMultipleSelectField::class
        ]);
    }

    /**
     *
     * Multiple select validation
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