<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Debug;
use SailCMS\Types\Fields\InputSelectField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class SelectField extends Field
{
    const SEARCHABLE = false;

    /**
     *
     * Override the constructor to pass a precision to the number
     *
     * @param  LocaleField            $labels
     * @param  array|Collection|null  $settings
     */
    public function __construct(LocaleField $labels, array|Collection|null $settings)
    {
        if ($labels->fr === "Selection")
            Debug::ray($settings);
        parent::__construct($labels, $settings);
    }

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