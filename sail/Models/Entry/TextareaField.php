<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\FieldCategory;
use SailCMS\Types\Fields\InputTextField;
use SailCMS\Types\LocaleField;

class TextareaField extends TextField
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
            'en' => 'Allows multiple lines of text.',
            'fr' => 'Permet plusieurs lignes de texte.'
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
            InputTextField::defaultSettings(true),
        ]);
    }
}