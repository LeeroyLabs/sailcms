<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\Fields\InputTextField;
use SailCMS\Types\StoringType;

class HTMLField extends TextField
{
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
}