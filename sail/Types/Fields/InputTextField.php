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

    /**
     *
     * Input text field from html input:text attributes
     *
     * @param LocaleField $labels
     * @param bool $required
     * @param int $max_length
     * @param int $min_length
     *
     */
    public function __construct(
        public readonly LocaleField $labels,
        public readonly bool        $required = false,
        public readonly int         $max_length = 0,
        public readonly int         $min_length = 0,
        public readonly string      $pattern = ''
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
            'required' => false,
            'max_length' => 0,
            'min_length' => 0,
            'pattern' => ''
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
            new InputSettings('max_length', InputSettings::INPUT_TYPE_NUMBER),
            new InputSettings('min_length', InputSettings::INPUT_TYPE_NUMBER),
            new InputSettings('pattern', InputSettings::INPUT_TYPE_REGEX, Collection::init(),)
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
     * @param mixed $content
     * @return Collection
     *
     */
    public function validate(mixed $content): Collection
    {
        $errors = Collection::init();

        if ($this->required && !$content) {
            $errors->push(self::FIELD_REQUIRED);
        }

        if ($this->max_length > 0 && strlen($content) > $this->max_length) {
            $errors->push(sprintf(self::FIELD_TOO_LONG, $this->max_length));
        }

        if ($this->min_length > 0 && strlen($content) < $this->min_length) {
            $errors->push(sprintf(self::FIELD_TOO_SHORT, $this->min_length));
        }

        preg_match("/$this->pattern/", $content, $matches);
        if ($this->pattern && empty($matches)) {
            $errors->push('regex errors');
        }

        return $errors;
    }

    /**
     *
     * When it's stored in the database
     *
     * @return stdClass
     *
     */
    public function toDBObject(): stdClass
    {
        return (object)[
            'labels' => $this->labels->toDBObject(),
            'settings' => [
                'required' => $this->required,
                'max_length' => $this->max_length,
                'min_length' => $this->min_length,
                'pattern' => $this->pattern
            ]
        ];
    }
}