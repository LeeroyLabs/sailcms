<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;
use stdClass;

class InputTextField extends Field
{
    /* Errors from 6120 to 6139 */
    public const FIELD_TOO_LONG = '6120: The content is too long (%s)';
    public const FIELD_TOO_SHORT = '6121: The content is too short (%s)';
    public const FIELD_PATTERN_NO_MATCH = '6122: The regex pattern does not matches /%s/';

    /**
     *
     * Input text field from html input:text attributes
     *
     * @param  LocaleField|null  $labels
     * @param  bool              $required
     * @param  int               $maxLength
     * @param  int               $minLength
     * @param  string            $pattern
     * @param  bool              $multiline
     */
    public function __construct(
        public readonly ?LocaleField $labels = null,
        public readonly bool         $required = false,
        public readonly int          $maxLength = 0,
        public readonly int          $minLength = 0,
        public readonly string       $pattern = '',
        public readonly bool         $multiline = false,
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
            'maxLength' => 0,
            'minLength' => 0,
            'pattern' => '',
            'multiline' => $multiline,
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
            new InputSettings('maxLength', InputSettings::INPUT_TYPE_NUMBER),
            new InputSettings('minLength', InputSettings::INPUT_TYPE_NUMBER),
            new InputSettings('pattern', InputSettings::INPUT_TYPE_STRING),
            new InputSettings('multiline', InputSettings::INPUT_TYPE_CHECKBOX),
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

        if ($content) {
            if ($this->maxLength > 0 && strlen($content) > $this->maxLength) {
                $errors->push(sprintf(self::FIELD_TOO_LONG, $this->maxLength));
            }

            if ($this->minLength > 0 && strlen($content) < $this->minLength) {
                $errors->push(sprintf(self::FIELD_TOO_SHORT, $this->minLength));
            }

            // The pattern is not used when it is a multiline input
            if ($this->pattern && !$this->multiline) {
                preg_match("/$this->pattern/", $content, $matches);
                if (empty($matches[0])) {
                    $errors->push(sprintf(self::FIELD_PATTERN_NO_MATCH, $this->pattern));
                }
            }
        }

        return $errors;
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
                'maxLength' => $this->maxLength,
                'minLength' => $this->minLength,
                'pattern' => $this->pattern,
                'multiline' => $this->multiline,
            ]
        ];
    }

    /**
     *
     * Cast to InputTextField
     *
     * @param  mixed  $value
     * @return InputTextField
     *
     */
    public function castTo(mixed $value): self
    {
        return new self(
            $value->labels,
            $value->settings->required ?? false,
            $value->settings->maxLength ?? 0,
            $value->settings->minLength ?? 0,
            $value->settings->pattern ?? '',
            $value->settings->multiline ?? false
        );
    }
}