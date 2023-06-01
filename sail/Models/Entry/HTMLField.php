<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\Fields\InputTextField;

class HTMLField extends TextField
{
    public const REPEATABLE = true;

    /* Errors from 6220 to 6239 */
    public const INVALID_TAGS = '6220: This string contains invalid tags';

    /**
     *
     * Description for field info
     *
     * @return string
     *
     */
    public function description(): string
    {
        return 'Field to implement a HMTL input.';
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