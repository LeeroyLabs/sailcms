<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class InputUrlField extends Field
{

    // @src https://uibakery.io/regex-library/url-regex-php
    public const DEFAULT_REGEX = '^https?:\\/\\/(?:www\\.)?[-a-zA-Z0-9@:%._\\+~#=]{1,256}\\.[a-zA-Z0-9()]{1,6}\\b(?:[-a-zA-Z0-9()@:%_\\+.~#?&\\/=]*)$';

    /* Errors from 6260 to 6279 */
    public const FIELD_PATTERN_NO_MATCH = '6201: The regex pattern does not matches /%s/';

    public function __construct(
        public readonly ?LocaleField $labels = null,
        public readonly bool         $required = false,
        public readonly string       $pattern = self::DEFAULT_REGEX
    )
    {
    }

    public static function defaultSettings(): Collection
    {
        return new Collection([
            'required' => false,
            'pattern' => self::DEFAULT_REGEX
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