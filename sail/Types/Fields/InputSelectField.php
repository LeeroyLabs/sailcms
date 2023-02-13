<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Models\Entry\SelectField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;
use stdClass;

class InputSelectField extends Field
{
    /* Errors from 6180 to 6199 */
    public const OPTIONS_INVALID = '6180: Option not valid';

    /**
     *
     *
     * Input select field from html select attributes
     *
     * @param LocaleField|null $labels
     * @param bool $required
     * @param bool $multiple
     * @param Collection $options
     */
    public function __construct(
        public readonly ?LocaleField $labels = null,
        public readonly bool $required = false,
        public readonly bool $multiple = false,
        public readonly Collection $options = new Collection([])
    ) {
    }

    /**
     *
     * Define default settings for a select field
     *
     * @return Collection
     *
     */
    public static function defaultSettings(bool $multiple = null): Collection
    {
        return new Collection([
            'required' => false,
            'multiple' => $multiple,
            'options' => new Collection([])
        ]);
    }

    /**
     *
     * Available properties of the settings
     *
     * @param array|null $options
     * @return Collection
     */
    public static function availableProperties(?array $options = null): Collection
    {
        if ( is_array($options) ) {
            $options = new Collection($options);
        }

        return new Collection([
            new InputSettings('required', InputSettings::INPUT_TYPE_CHECKBOX),
            new InputSettings('multiple', InputSettings::INPUT_TYPE_CHECKBOX),
            new InputSettings('options', InputSettings::INPUT_TYPE_OPTIONS, $options)
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
        return StoringType::STRING->value . " or " . StoringType::ARRAY->value;
    }

    /**
     *
     * Select field validation
     *
     * @param  mixed  $content
     * @return Collection
     *
     */
    public function validate(mixed $content): Collection
    {
        $errors = Collection::init();

        if ($this->required && ($content === "" || $content === [])) {
            $errors->push(self::FIELD_REQUIRED);
        }

        if (is_string($content)) {
            if (!array_key_exists($content, $this->options->unwrap())) {
                $errors->push(self::OPTIONS_INVALID);
            }
        }else{
            foreach ($content->unwrap() as $option) {
                if (!array_key_exists($option, $this->options->unwrap())) {
                    $errors->push(self::OPTIONS_INVALID);
                }
            }
        }
        return $errors;
    }

    /**
     *
     * Cast to simpler form from InputSelectField
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
                'multiple' => $this->multiple,
                'options' => $this->options->unwrap()
            ]
        ];
    }

    /**
     *
     * Cast to InputSelectField
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
            $value->settings->multiple,
            $value->settings->options
        );
    }
}