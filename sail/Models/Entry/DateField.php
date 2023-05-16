<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\Fields\InputDateField;
use SailCMS\Types\StoringType;

class DateField extends Field
{
    const DATE_FORMAT_DEFAULT = 'Y-m-d';

    /**
     *
     * Description for field info
     *
     * @return string
     *
     */
    public function description(): string
    {
        return 'Field to implement an date html input.';
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
        return StoringType::INTEGER->value;
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
            InputDateField::defaultSettings()
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
            InputDateField::class
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
        // Nothing to implement
        return null;
    }

    /**
     * Convert to Timestamp
     *
     * @param  mixed  $content
     * @return mixed
     */
    public function convert(mixed $content): mixed
    {
        $format = $this->configs->first->format ?? self::DATE_FORMAT_DEFAULT;
        $date = \DateTime::createFromFormat($format, $content);
        
        return $date->getTimestamp();
    }

    /**
     * Parse to Date string from timestamp
     *
     * @param  mixed  $content
     * @return mixed
     */
    public function parse(mixed $content): mixed
    {
        $format = $this->configs->first->format ?? self::DATE_FORMAT_DEFAULT;
        $date = new \DateTime();
        $date = $date->setTimestamp($content);

        return $date->format($format);
    }
}