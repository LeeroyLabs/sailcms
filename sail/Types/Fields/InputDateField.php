<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;
use stdClass;

class InputDateField extends Field
{
    /* Errors from 6141 to 6159 */
    public const FIELD_TOO_BIG = '6141: The value is too big (%s)';
    public const FIELD_TOO_SMALL = '6142: The value is too small (%s)';

    /**
     *
     *
     * Input text field from html input:number attributes
     *
     * @param  LocaleField|null  $labels
     * @param  string|null       $format
     * @param  bool              $required
     * @param  string|null       $min
     * @param  string|null       $max
     *
     */
    public function __construct(
        public readonly ?LocaleField $labels = null,
        public readonly ?string      $format = 'timestamp',
        public readonly bool         $required = false,
        public readonly ?string      $min = null,
        public readonly ?string      $max = null,
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
            'format' => 'timestamp',
            'required' => false,
            'min' => null,
            'max' => null,
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
            new InputSettings('format', InputSettings::INPUT_TYPE_STRING),
            new InputSettings('required', InputSettings::INPUT_TYPE_CHECKBOX),
            new InputSettings('min', InputSettings::INPUT_TYPE_NUMBER),
            new InputSettings('max', InputSettings::INPUT_TYPE_NUMBER),
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
        return StoringType::DATE->value;
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

        $contentCasted = strtotime($content);
        $minCasted = $this->min ? strtotime($this->min) : 0;
        $maxCasted = $this->max ? strtotime($this->max) : 0;

        // Since "0" is treat as false, the condition for empty is stricter
        if ($this->required && $content === "") {
            $errors->push(self::FIELD_REQUIRED);
        }

        if ($minCasted > 0 && $contentCasted < $minCasted) {
            $errors->push(sprintf(self::FIELD_TOO_SMALL, $this->min));
        }

        if ($maxCasted > 0 && $contentCasted > $maxCasted) {
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
                'max' => $this->max
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