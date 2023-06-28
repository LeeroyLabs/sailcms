<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\FieldCategory;
use SailCMS\Types\Fields\InputHTMLField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class HTMLField extends Field
{
    public const SEARCHABLE = true;
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
            'en' => 'Allows rich HTML using a Word-like utility.',
            'fr' => 'Permet l\'édition de texte riche HTML avec un outil style Word.'
        ]);
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
            InputHTMLField::defaultSettings(true),
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
        return null;
    }

    /**
     * @return string
     */
    public function category(): string
    {
        return FieldCategory::TEXT->value;
    }

    /**
     * @return string
     */
    public function storingType(): string
    {
        return StoringType::STRING->value;
    }

    /**
     * @return void
     */
    protected function defineBaseConfigs(): void
    {
        $this->baseConfigs = new Collection([
            InputHTMLField::class
        ]);
    }
}