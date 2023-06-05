<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;
use stdClass;

class InputNumberField extends Field
{
    /* Errors from 6200 to 6219 */
    public const FIELD_TOO_BIG = '6201: The value is too big (%s)';
    public const FIELD_TOO_SMALL = '6202: The value is too small (%s)';

    /**
     *
     *
     * Input text field from html input:number attributes
     *
     * @param  LocaleField|null  $labels
     * @param  bool              $required
     * @param  float             $min
     * @param  float             $max
     * @param  float|int         $step
     *
     */
    public function __construct(
        public readonly ?LocaleField $labels = null,
        public readonly bool $required = false,
        public readonly float $min = 0,
        public readonly float $max = 0,
        public readonly float|int $step = 1
    ) {
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
            'step' => 1
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
            new InputSettings('step', InputSettings::INPUT_TYPE_NUMBER)
        ]);
    }

    /**
     *
     * Get the type of how it's store in the database
     *  > it could be an integer or a float
     *
     * @return string
     */
    public static function storingType(): string
    {
        return StoringType::INTEGER->value . " or " . StoringType::FLOAT->value;
    }

    /**
     *
     * Input text field validation
     *
     * @param  mixed  $content
     * @return Collection
     *
     */
    public function validate(mixed $content): Collection
    {
        $errors = Collection::init();

        $contentCasted = is_float($this->step) ? (float)$content : (integer)$content;

        // Since "0" is treat as false, the condition for empty is stricter
        if ($this->required && $content === "") {
            $errors->push(self::FIELD_REQUIRED);
        }

        if ($contentCasted < $this->min) {
            $errors->push(sprintf(self::FIELD_TOO_SMALL, $this->min));
        }

        if ($this->max > 0 && $contentCasted > $this->max) {
            $errors->push(sprintf(self::FIELD_TOO_BIG, $this->max));
        }

        return $errors;
    }

    /**
     *
     * Cast to simpler form from InputNumberField
     *
     * @return stdClass
     *
     */
    public function castFrom(): stdClass
    {
        return (object)[
            'labels' => $this->labels->castFrom(),
            'settings' => [
                'required' => $this->required,
                'min' => $this->min,
                'max' => $this->max,
                'step' => $this->step
            ]
        ];
    }

    /**
     *
     * Cast to InputNumberField
     *
     * @param  mixed  $value
     * @return Field
     *
     */
    public function castTo(mixed $value): Field
    {
        return new self(
            $value->labels,
            $value->settings->required,
            $value->settings->min,
            $value->settings->max,
            $value->settings->step
        );
    }
}