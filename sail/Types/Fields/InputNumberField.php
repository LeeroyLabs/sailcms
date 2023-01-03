<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;
use stdClass;

class InputNumberField extends Field
{
    /* Errors from 6140 to 6159 */
    protected const FIELD_TOO_BIG = '6140: The value is too big';
    protected const FIELD_TOO_SMALL = '6141: The value is too small';
    protected const FIELD_HAS_NEGATIVE_NUMBER = '6142: The value should not be negative.';

    /**
     *
     * Input text field from html input:number attributes
     *
     * @param LocaleField $labels
     * @param bool $required
     * @param int $min
     * @param int $max
     * @param bool $negative_number
     *
     */
    public function __construct(
        public readonly LocaleField $labels,
        public readonly bool        $required = false,
        public readonly int         $min = 0,
        public readonly int         $max = 0,
        public readonly bool        $negative_number = true,
    )
    {
    }

    /**
     *
     * Define default settings for a Input Text Field
     *
     * @return Collection
     *
     */
    public static function defaultSettings(): Collection
    {
        return new Collection([
            'required' => false,
            'min' => 0,
            'max' => 0,
            'negative_number' => true
        ]);
    }

    /**
     *
     * Available properties of the settings
     *
     * @return Collection
     *
     */
    public static function availableProperties(): Collection
    {
        return new Collection([
            new InputSettings('required', InputSettings::INPUT_TYPE_CHECKBOX),
            new InputSettings('min', InputSettings::INPUT_TYPE_NUMBER),
            new InputSettings('max', InputSettings::INPUT_TYPE_NUMBER),
            new InputSettings('negative_number', InputSettings::INPUT_TYPE_CHECKBOX),
        ]);
    }

    /**
     *
     * Get the type of how it's store in the database
     *
     * @return string
     */
    public static function storingType(): string
    {
        return StoringType::INTEGER->value;
    }

    /**
     *
     * Input text field validation
     *  // TODO cast the content according to the type + add type
     *
     * @param mixed $content
     * @return Collection
     *
     */
    public function validate(mixed $content): Collection
    {
        $errors = Collection::init();

        if ($this->required && !$content) {
            $errors->push(self::FIELD_REQUIRED);
        }

        if ($this->min > 0 && (integer)$content < $this->min) {
            $errors->push(self::FIELD_TOO_SMALL . ' (' . $this->min . ').');
        }

        if ($this->max > 0 && (integer)$content > $this->max) {
            $errors->push(self::FIELD_TOO_BIG . ' (' . $this->max . ').');
        }

        if (!$this->negative_number && (integer)$content < 0) {
            $errors->push(self::FIELD_HAS_NEGATIVE_NUMBER);
        }

        return $errors;
    }

    /**
     *
     * When it's stored in the database
     *
     * @return stdClass
     *
     */
    public function toDBObject(): stdClass
    {
        return (object)[
            'labels' => $this->labels->toDBObject(),
            'settings' => [
                'required' => $this->required,
                'min' => $this->min,
                'max' => $this->max,
                'negative_number' => $this->negative_number,
            ]
        ];
    }
}