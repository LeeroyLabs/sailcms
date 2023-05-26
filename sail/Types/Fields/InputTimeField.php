<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Models\Entry\TimeField;

class InputTimeField extends InputDateField
{
    /* Errors from 6141 to 6159 */
    public const FIELD_TOO_BIG = '6143: The time is greater than %s';
    public const FIELD_TOO_SMALL = '6144: The time is smaller than %s';
    public const MULTIPLE = true;

    public static function defaultSettings(): Collection
    {
        return new Collection([
            'format' => TimeField::TIME_FORMAT_DEFAULT,
            'required' => false,
            'min' => null,
            'max' => null,
        ]);
    }
}