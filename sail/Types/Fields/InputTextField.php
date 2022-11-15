<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Locale;
use SailCMS\Types\LocaleField;

class InputTextField extends Field
{
    public function __construct(
        public readonly LocaleField $label,
        public readonly bool $required = false,
        public readonly int $maxLength = 0,
        public readonly int $minLength = 0
    ) {
    }

    public function validate(mixed $content): Collection
    {
        $errors = new Collection([]);
        if ($this->required && !$content) {
            $errors->push($this->label->{Locale::$current} . ' ' . Locale::translate('fields.errors.is_required') . '.');
        }

        if ($this->maxLength > 0 && strlen($content) > $this->maxLength) {
            $errors->push($this->label->{Locale::$current} . ' ' . Locale::translate('fields.errors.max_length') . '(' . $this->maxLength . ').');
        }

        if ($this->minLength > 0 && strlen($content) > $this->minLength) {
            $errors->push($this->label->{Locale::$current} . ' ' . Locale::translate('fields.errors.min_length') . '(' . $this->minLength . ').');
        }

        return $errors;
    }

    public function toDBObject(): array
    {
        return [
            'label' => $this->label->toDBObject(),
            'required' => $this->required,
            'max_length' => $this->maxLength,
            'min_length' => $this->minLength
        ];
    }
}