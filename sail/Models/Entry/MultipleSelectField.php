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
    public const MULTIPLE = true;

    /**
     *
     * Description of the Multiple Select Field
     *
     * @return LocaleField
     *
     */
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

    /**
     *
     * Storing type of the field
     *
     * @return string
     *
     */
    public function storingType(): string
    {
        return StoringType::STRING->value;
    }

    /**
     *
     * Default setting for the Multiple Select Field
     *
     * @return Collection
     *
     */
    public function defaultSettings(): Collection
    {
        return new Collection([
            InputSelectField::defaultSettings()
        ]);
    }

    /**
     *
     * List of inputs for the Multiple Select Field
     *
     * @return void
     *
     */
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