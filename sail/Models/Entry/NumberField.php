<?php

namespace SailCMS\Models\Entry;

use Exception;
use SailCMS\Collection;
use SailCMS\Types\Fields\InputNumberField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class NumberField extends Field
{
    public const PRECISION_MAX_LIMIT = 14;
    public const INVALID_PRECISION = '6200: Number field precision must be between 0 and ' . self::PRECISION_MAX_LIMIT;

    public int $precision = 0;

    /**
     *
     * Override the constructor to pass a precision to the number
     *
     * @param LocaleField $labels
     * @param array|Collection|null $settings
     * @param int $precision
     * @throws Exception
     */
    public function __construct(LocaleField $labels, array|Collection|null $settings, int $precision = 0)
    {
        // Validate precision to avoid errors
        if ($precision < 0 || $precision > self::PRECISION_MAX_LIMIT) {
            throw new Exception(self::INVALID_PRECISION);
        }

        $this->precision = $precision;
        if ($precision > 0) {
            $settings[0]['step'] = 1 / pow(10, $precision);
        }

        parent::__construct($labels, $settings);
    }

    /**
     *3
     * Description for field info
     *
     * @return string
     *
     */
    public function description(): string
    {
        return 'Field to implement a number html input.';
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
        if ($this->precision > 0) {
            return StoringType::FLOAT->value;
        } else {
            return StoringType::INTEGER->value;
        }
    }

    /**
     *
     * Sets the default settings from the input number field
     *
     * @return Collection
     *
     */
    public function defaultSettings(): Collection
    {
        return new Collection([
            InputNumberField::defaultSettings()
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
            InputNumberField::class
        ]);
    }

    /**
     *
     * There is nothing extra to validate for the text field
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