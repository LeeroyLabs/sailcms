<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\FieldCategory;
use SailCMS\Types\Fields\Field as InputField;
use SailCMS\Types\Fields\InputSelectField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class MultipleSelectField extends Field
{
    public const SEARCHABLE = false;
    public const REPEATABLE = true;

    /**
     *
     * Override the constructor to pass a precision to the multiple select
     *
     * @param  LocaleField            $labels
     * @param  array|Collection|null  $settings
     * @param  bool                   $repeater
     */
    public function __construct(LocaleField $labels, array|Collection|null $settings, bool $repeater = true)
    {
        parent::__construct($labels, $settings, $repeater);
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
        return StoringType::STRING->value;
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
     * Multiple select validation
     *
     * @param  mixed  $content
     * @return Collection|null
     *
     */
    protected function validate(mixed $content): ?Collection
    {
        $errors = Collection::init();

        if ($this->isRequired() && count($content) === 0) {
            $errors->push(new Collection([InputField::FIELD_REQUIRED]));
        }
        return $errors;
    }
}