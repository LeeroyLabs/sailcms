<?php

namespace SailCMS\Types\Fields;

use Exception;
use SailCMS\Collection;
use SailCMS\Locale;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;
use stdClass;

class InputNumberField extends Field
{
    /**
     *
     * Input text field from html input:number attributes
     *
     * @param LocaleField $labels
     * @param bool $required
     * @param int $min
     * @param int $max
     * @param bool $negative_number
     *
     */
    public function __construct(
        public readonly LocaleField $labels,
        public readonly bool $required = false,
        public readonly int $min = 0,
        public readonly int $max = 0,
        public readonly bool $negative_number = false,
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
            'min' => 0,
            'max' => 0,
            'negative_number' => false
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
            new InputSettings('min', InputSettings::INPUT_TYPE_NUMBER),
            new InputSettings('max', InputSettings::INPUT_TYPE_NUMBER),
            new InputSettings('negative_number', InputSettings::INPUT_TYPE_CHECKBOX),
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
        return StoringType::INTEGER->value;
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

        if ($this->min > 0 && strlen($content) < $this->min) {
            try {
                $errorMessage = Locale::translate('fields.errors.min_number');
            } catch (Exception $exception) {
                $errorMessage = 'number not enough big';
            }

            $errors->push($this->labels->{$currentLocale} . ' ' . $errorMessage . ' (' . $this->min . ').');
        }

        if ($this->max > 0 && strlen($content) > $this->max) {
            try {
                $errorMessage = Locale::translate('fields.errors.max_number');
            } catch (Exception $exception) {
                $errorMessage = 'number is too big';
            }

            $errors->push($this->labels->{$currentLocale} . ' ' . $errorMessage . ' (' . $this->max . ').');
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
                'min' => $this->min,
                'max' => $this->max,
                'negative_number' => $this->negative_number,
            ]
        ];
    }
}