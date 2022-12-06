<?php

namespace SailCMS\Types\Fields;

use Exception;
use SailCMS\Collection;
use SailCMS\Locale;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;
use stdClass;

class InputTextField extends Field
{
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
        public readonly int         $min_length = 0
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
            'min_length' => 0
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
        ]);
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
        $currentLocale = Locale::$current ?? 'en';

        if ($this->required && !$content) {
            try {
                $errorMessage = Locale::translate('fields.errors.is_required');
            } catch (Exception $exception) {
                $errorMessage = 'is required';
            }

            $errors->push($this->labels->{$currentLocale} . ' ' . $errorMessage . '.');
        }

        if ($this->max_length > 0 && strlen($content) > $this->max_length) {
            try {
                $errorMessage = Locale::translate('fields.errors.max_length');
            } catch (Exception $exception) {
                $errorMessage = 'is too long';
            }

            $errors->push($this->labels->{$currentLocale} . ' ' . $errorMessage . '(' . $this->max_length . ').');
        }

        if ($this->min_length > 0 && strlen($content) < $this->min_length) {
            try {
                $errorMessage = Locale::translate('fields.errors.min_length');
            } catch (Exception $exception) {
                $errorMessage = 'is too short';
            }

            $errors->push($this->labels->{$currentLocale} . ' ' . $errorMessage . '(' . $this->min_length . ').');
        }

        return $errors;
    }

    /**
     *
     * When stored in the database
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
                'min_length' => $this->min_length
            ]
        ];
    }

    public static function storingType(): string
    {
        return StoringType::STRING->value;
    }
}