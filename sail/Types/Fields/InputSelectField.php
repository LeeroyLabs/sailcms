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
    /**
     *
     *
     * Input text field from html input:select attributes
     *
     * @param LocaleField|null $labels
     * @param bool $required
     * @param bool $multiple
     * @param array $options
     */
    public function __construct(
        public readonly ?LocaleField $labels = null,
        public readonly bool $required = false,
        public readonly bool $multiple = false,
        public readonly array $options = []
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
            'multiple' => false,
            'options' => []
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
        return new Collection([
            new InputSettings('required', InputSettings::INPUT_TYPE_CHECKBOX),
            new InputSettings('multiple', InputSettings::INPUT_TYPE_CHECKBOX),
            new InputSettings('options', InputSettings::INPUT_TYPE_OPTION, new Collection($options))
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
        return StoringType::ARRAY->value;
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

        // Since "0" is treat as false, the condition for empty is stricter
        if ($this->required && $content === "") {
            $errors->push(self::FIELD_REQUIRED);
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
                'multiple' => $this->multiple
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
            $value->settings->multiple
        );
    }
}