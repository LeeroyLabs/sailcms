<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Locale;
use SailCMS\Types\LocaleField;
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
        public readonly bool $required = false,
        public readonly int $max_length = 0,
        public readonly int $min_length = 0
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
            new Input('required', Input::INPUT_TYPE_CHECKBOX),
            new Input('max_length', Input::INPUT_TYPE_NUMBER),
            new Input('min_length', Input::INPUT_TYPE_NUMBER),
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
        if ($this->required && !$content) {
            $errors->push($this->labels->{Locale::$current} . ' ' . Locale::translate('fields.errors.is_required') . '.');
        }

        if ($this->max_length > 0 && strlen($content) > $this->max_length) {
            $errors->push($this->labels->{Locale::$current} . ' ' . Locale::translate('fields.errors.max_length') . '(' . $this->maxLength . ').');
        }

        if ($this->min_length > 0 && strlen($content) < $this->min_length) {
            $errors->push($this->labels->{Locale::$current} . ' ' . Locale::translate('fields.errors.min_length') . '(' . $this->minLength . ').');
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
            'configs' => [
                'required' => $this->required,
                'max_length' => $this->max_length,
                'min_length' => $this->min_length
            ]
        ];
    }
}