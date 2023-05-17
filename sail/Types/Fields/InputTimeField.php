<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;
use stdClass;

class InputTimeField extends Field
{

    /**
     *
     *
     * Input date field from html input:time attributes
     *
     * @param  LocaleField|null  $labels
     * @param  bool              $required
     * @param  string|null       $min
     * @param  string|null       $max
     *
     */
    public function __construct(
        public readonly ?LocaleField $labels = null,
        public readonly bool         $required = false,
        public readonly ?string      $min = null,
        public readonly ?string      $max = null,
    )
    {
    }

    public static function defaultSettings(): Collection
    {
        return new Collection([
            'required' => false,
            'min' => null,
            'max' => null,
        ]);
    }

    public static function availableProperties(): Collection
    {
        return new Collection([
            new InputSettings('required', InputSettings::INPUT_TYPE_CHECKBOX),
            new InputSettings('min', InputSettings::INPUT_TYPE_STRING),
            new InputSettings('max', InputSettings::INPUT_TYPE_STRING),
        ]);
    }

    public static function storingType(): string
    {
        return StoringType::STRING->value;
    }

    public function validate(mixed $content): Collection
    {
        $errors = Collection::init();

        // TODO

        return $errors;
    }


    /**
     *
     * Cast to simpler form from InputTimeField object
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
     * Cast to InputTimeField
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
            $value->settings->max
        );
    }
}