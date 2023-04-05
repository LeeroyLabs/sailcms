<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class InputUrlField extends Field
{
    /* Errors from 6240 to 6259 */
    public const FIELD_PATTERN_NO_MATCH = '6201: The regex pattern does not matches /%s/';

    public function __construct(
        public readonly ?LocaleField $labels = null,
        public readonly bool         $required = false,
        public readonly string       $pattern = ''
    )
    {
    }

    public static function defaultSettings(): Collection
    {
        return new Collection([
            'required' => false,
            'pattern' => ''
        ]);
    }

    public static function availableProperties(): Collection
    {
        return new Collection([
            new InputSettings('required', InputSettings::INPUT_TYPE_CHECKBOX),
            new InputSettings('pattern', InputSettings::INPUT_TYPE_REGEX),
        ]);
    }

    public static function storingType(): string
    {
        return StoringType::STRING->value;
    }

    public function validate(mixed $content): Collection
    {
        $errors = Collection::init();

        if ($this->required && !$content) {
            $errors->push(self::FIELD_REQUIRED);
        }

        if ($content) {
            if ($this->pattern) {
                preg_match("/$this->pattern/", $content, $matches);
                if (empty($matches[0])) {
                    $errors->push(sprintf(self::FIELD_PATTERN_NO_MATCH, $this->pattern));
                }
            }
        }

        return $errors;
    }
}