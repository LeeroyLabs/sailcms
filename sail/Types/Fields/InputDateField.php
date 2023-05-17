<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Models\Entry\DateField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;
use stdClass;

class InputDateField extends Field
{
    /* Errors from 6141 to 6159 */
    public const FIELD_TOO_BIG = '6141: The date is greater than %s';
    public const FIELD_TOO_SMALL = '6142: The date is smaller than %s';

    /**
     *
     *
     * Input date field from html input:date attributes
     *
     * @param  LocaleField|null  $labels
     * @param  string|null       $format  Should be a format from https://www.php.net/manual/en/datetime.format.php
     * @param  bool              $required
     * @param  string|null       $min
     * @param  string|null       $max
     *
     */
    public function __construct(
        public readonly ?LocaleField $labels = null,
        public readonly ?string      $format = null,
        public readonly bool         $required = false,
        public readonly ?string      $min = null,
        public readonly ?string      $max = null,
    )
    {
    }

    /**
     *
     * Define default settings for a Input Date Field
     *
     * @return Collection
     *
     */
    public static function defaultSettings(): Collection
    {
        return new Collection([
            'format' => DateField::DATE_FORMAT_DEFAULT,
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
            new InputSettings('min', InputSettings::INPUT_TYPE_STRING),
            new InputSettings('max', InputSettings::INPUT_TYPE_STRING),
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
        return StoringType::STRING->value;
    }

    /**
     *
     * Input date field validation
     *
     * @param  mixed  $content
     * @return Collection
     *
     */
    public function validate(mixed $content): Collection
    {
        $errors = Collection::init();

        $contentCasted = \DateTime::createFromFormat($this->format, $content);
        $minCasted = $this->min ? \DateTime::createFromFormat($this->format, $this->min) : "";
        $maxCasted = $this->max ? \DateTime::createFromFormat($this->format, $this->max) : "";

        // Since "0" is treat as false, the condition for empty is stricter
        if ($this->required && $content === "") {
            $errors->push(self::FIELD_REQUIRED);
        }

        if ($minCasted !== "" && $contentCasted < $minCasted) {
            $errors->push(sprintf(static::FIELD_TOO_SMALL, $this->min));
        }

        if ($maxCasted !== "" && $contentCasted > $maxCasted) {
            $errors->push(sprintf(static::FIELD_TOO_BIG, $this->max));
        }

        return $errors;
    }

    /**
     *
     * Cast to simpler form from InputDateField
     *
     * @return stdClass
     *
     */
    public function castFrom(): stdClass
    {
        return (object)[
            'labels' => $this->labels->castFrom(),
            'settings' => [
                'format' => $this->format,
                'required' => $this->required,
                'min' => $this->min,
                'max' => $this->max
            ]
        ];
    }

    /**
     *
     * Cast to InputDateField
     *
     * @param  mixed  $value
     * @return Field
     *
     */
    public function castTo(mixed $value): Field
    {
        return new self(
            $value->labels,
            $value->settings->format,
            $value->settings->required,
            $value->settings->min,
            $value->settings->max
        );
    }
}