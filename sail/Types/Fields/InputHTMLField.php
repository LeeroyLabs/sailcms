<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;
use stdClass;

class InputHTMLField extends Field
{
    /**
     *
     * Input text field from html input:text attributes
     *
     * @param  LocaleField|null  $labels
     * @param  bool              $required
     *
     */
    public function __construct(
        public readonly ?LocaleField $labels = null,
        public readonly bool $required = false
    ) {
    }

    /**
     *
     * Define default settings for a Input Text Field
     *
     * @param  bool  $multiline
     * @return Collection
     *
     */
    public static function defaultSettings(bool $multiline = false): Collection
    {
        return new Collection([
            'required' => false,
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
            new InputSettings('required', InputSettings::INPUT_TYPE_CHECKBOX)
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
     * Input text field validation
     *
     * @param  mixed  $content
     * @return Collection
     *
     */
    public function validate(mixed $content): Collection
    {
        $errors = Collection::init();

        if ($this->required && !$content) {
            $errors->push(self::FIELD_REQUIRED);
        }

        return $errors;
    }

    /**
     *
     * Cast to InputTextField
     *
     * @param  mixed  $value
     * @return InputHTMLField
     *
     */
    public function castTo(mixed $value): self
    {
        return new self(
            $value->labels,
            $value->settings->required ?? false
        );
    }
}