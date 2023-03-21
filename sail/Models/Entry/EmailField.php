<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\Fields\InputEmailField;
use SailCMS\Types\StoringType;

class EmailField extends Field
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
        return 'Field to implement an email input.';
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
     * @param mixed $content
     * @return Collection|null
     *
     */
    protected function validate(mixed $content): ?Collection
    {
        // Nothing to implement
        return null;
    }
}