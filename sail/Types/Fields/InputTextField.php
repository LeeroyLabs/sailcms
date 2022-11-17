<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Locale;
use SailCMS\Types\LocaleField;

class InputTextField extends Field
{
    public function __construct(
        public readonly LocaleField $labels,
        public readonly bool $required = false,
        public readonly int $maxLength = 0,
        public readonly int $minLength = 0
    ) {
    }

    public static function defaultSettings(): Collection
    {
        return new Collection([
            'required' => false,
            'maxLength' => 0,
            'minLength' => 0
        ]);
    }

    public static function availableProperties(): Collection
    {
        return new Collection([
            new Input('required', Input::INPUT_TYPE_CHECKBOX),
            new Input('maxLength', Input::INPUT_TYPE_NUMBER),
            new Input('minLength', Input::INPUT_TYPE_NUMBER),
        ]);
    }

    public function validate(mixed $content): Collection
    {
        $errors = Collection::init();
        if ($this->required && !$content) {
            $errors->push($this->labels->{Locale::$current} . ' ' . Locale::translate('fields.errors.is_required') . '.');
        }

        if ($this->maxLength > 0 && strlen($content) > $this->maxLength) {
            $errors->push($this->labels->{Locale::$current} . ' ' . Locale::translate('fields.errors.max_length') . '(' . $this->maxLength . ').');
        }

        if ($this->minLength > 0 && strlen($content) < $this->minLength) {
            $errors->push($this->labels->{Locale::$current} . ' ' . Locale::translate('fields.errors.min_length') . '(' . $this->minLength . ').');
        }

        return $errors;
    }

    public function toDBObject(): array
    {
        return [
            'label' => $this->labels->toDBObject(),
            'required' => $this->required,
            'max_length' => $this->maxLength,
            'min_length' => $this->minLength
        ];
    }
}