<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\FieldCategory;
use SailCMS\Types\Fields\InputTextField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class HTMLField extends TextField
{
    /* Errors from 6220 to 6239 */
    public const INVALID_TAGS = '6220: This string contains invalid tags';

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
            InputTextField::defaultSettings(true),
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
        $errors = Collection::init();

        $validTags = ['p', 'a', 'div', 'i', 'strong'];
        preg_match_all('~<(?P<tag>[^\/][^>]*?)>~', $content, $htmlTags);

        if (count(array_diff($htmlTags['tag'], $validTags)) > 0) {
            $errors->push(self::INVALID_TAGS);
        }

        return $errors;
    }
}