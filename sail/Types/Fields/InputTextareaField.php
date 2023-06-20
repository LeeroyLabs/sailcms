<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;
use stdClass;

class InputTextareaField extends InputTextField
{

    /**
     *
     * Input text field from html input:text attributes
     *
     * @param  LocaleField|null  $labels
     * @param  bool              $required
     * @param  int               $minLength
     * @param  int               $maxLength
     * @param  string            $pattern
     * @param  int               $rows
     *
     */
    public function __construct(
        public readonly ?LocaleField $labels = null,
        public readonly bool         $required = false,
        public readonly int          $minLength = 0,
        public readonly int          $maxLength = 0,
        public readonly string       $pattern = '',
        public readonly int          $rows = 2
    )
    {
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
            'minLength' => 0,
            'maxLength' => 0,
            'pattern' => '',
            'rows' => 2
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
            new InputSettings('minLength', InputSettings::INPUT_TYPE_NUMBER),
            new InputSettings('maxLength', InputSettings::INPUT_TYPE_NUMBER),
            new InputSettings('pattern', InputSettings::INPUT_TYPE_STRING, Collection::init(), true),
            new InputSettings('rows', InputSettings::INPUT_TYPE_NUMBER)
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
     * Cast to simpler form from InputTextField
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
                'minLength' => $this->minLength,
                'maxLength' => $this->maxLength,
                'pattern' => $this->pattern,
                'rows' => $this->rows
            ]
        ];
    }

    /**
     *
     * Cast to InputTextField
     *
     * @param  mixed  $value
     * @return self
     *
     */
    public function castTo(mixed $value): self
    {
        return new self(
            $value->labels,
            $value->settings->required ?? false,
            $value->settings->minLength ?? 0,
            $value->settings->maxLength ?? 0,
            $value->settings->pattern ?? '',
            $value->settings->rows ?? 2
        );
    }
}