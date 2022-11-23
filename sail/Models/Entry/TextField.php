<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\Fields\InputTextField;

class TextField extends Field
{
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
     * @param Collection $content
     * @return Collection|null
     *
     */
    protected function validate(Collection $content): ?Collection
    {
        // Nothing to implement
        return null;
    }
}