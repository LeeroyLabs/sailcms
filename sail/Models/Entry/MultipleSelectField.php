<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\Fields\InputSelectField;

class MultipleSelectField extends SelectField
{
    /**
     *
     * Description for field info
     *
     * @return string
     *
     */
    public function description(): string
    {
        return 'Field to implement a multiple input.';
    }

    /**
     *
     * Sets the default settings from the input select field
     *
     * @return Collection
     *
     */
    public function defaultSettings(): Collection
    {
        return new Collection([
            InputSelectField::defaultSettings(true)
        ]);
    }
}