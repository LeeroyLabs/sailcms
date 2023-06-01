<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\FieldCategory;
use SailCMS\Types\Fields\InputEmailField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class EmailField extends Field
{
    public const REPEATABLE = true;

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
            'en' => 'Allows an email address and validates it.',
            'fr' => 'Permet une adresse courriel et qui la valide.'
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
     * Sets the default settings from the input email field
     *
     * @return Collection
     *
     */
    public function defaultSettings(): Collection
    {
        return new Collection([
            InputEmailField::defaultSettings()
        ]);
    }

    /**
     *
     * The text field contain only an input email field
     *
     * @return void
     */
    protected function defineBaseConfigs(): void
    {
        $this->baseConfigs = new Collection([
            InputEmailField::class
        ]);
    }

    /**
     *
     * There is nothing extra to validate for the email field
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