<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
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

    public function description(): string
    {
        return 'Field to implement a multiple input.';
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