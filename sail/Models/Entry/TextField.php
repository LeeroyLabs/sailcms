<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\FieldCategory;
use SailCMS\Types\Fields\InputTextField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class TextField extends Field
{
    public const SEARCHABLE = true;

    /**
     *
     * Description for field info
     *
     * @return LocaleField
     *
     */
    public function description(): LocaleField
    {
        return new LocaleField([
            'en' => 'Generic text input.',
            'fr' => 'Champ texte générique.'
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

    /**
     *
     * Returns the storing type
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
     * Sets the default settings from the input text field
     *
     * @return Collection
     *
     */
    public function defaultSettings(): Collection
    {
        return new Collection([
            InputTextField::defaultSettings()
        ]);
    }

    /**
     *
     * The text field contain only an input text field
     *
     * @return void
     */
    protected function defineBaseConfigs(): void
    {
        $this->baseConfigs = new Collection([
            InputTextField::class
        ]);
    }

    /**
     *
     * There is nothing extra to validate for the text field
     *
     * @param  mixed  $content
     * @return Collection|null
     *
     */
    protected function validate(mixed $content): ?Collection
    {
        // Nothing to implement
        return null;
    }
}