<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;
use stdClass;

class InputSelectField extends Field
{
    /* Errors from 6180 to 6199 */
    public const OPTIONS_INVALID = '6180: Option not valid';
    public const MULTIPLE = false;

    /**
     *
     *
     * Input select field from html select attributes
     *
     * @param LocaleField|null $labels
     * @param bool $required
     * @param Collection $options
     */
    public function __construct(
        public readonly ?LocaleField $labels = null,
        public readonly bool $required = false,
        public readonly Collection $options = new Collection([])
    ) {
    }

    /**
     *
     * Define default settings for a select field
     *
     * @return Collection
     */
    public static function defaultSettings(): Collection
    {
        return new Collection([
            'required' => false,
            'options' => new Collection([])
        ]);
    }

    /**
     *
     * Available properties of the settings
     *
     * @param array|Collection|null $options
     * @return Collection
     */
    public static function availableProperties(array|Collection|null $options = null): Collection
    {
        if ( is_array($options) ) {
            $options = new Collection($options);
        }

        return new Collection([
            new InputSettings('required', InputSettings::INPUT_TYPE_CHECKBOX),
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
        return StoringType::STRING->value;
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

        if ($this->required && $content === "") {
            $errors->push(self::FIELD_REQUIRED);
        }

        if (!array_key_exists($content, $this->options->unwrap())) {
            $errors->push(self::OPTIONS_INVALID);
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
            $value->settings->options
        );
    }
}