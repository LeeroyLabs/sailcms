<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Contracts\DatabaseType;
use SailCMS\Types\LocaleField;

abstract class Field implements DatabaseType
{
    /* ERRORS */
    const INVALID_VALUE_FOR_TYPE = 'Invalid value %s for %s';

    public function __construct(
        public readonly LocaleField $labels,
        public readonly bool $required = false
    ) {
    }

    /**
     * @return array
     */
    public function toDBObject(): array
    {
        return [
            'label' => $this->labels->toDBObject(),
            'required' => $this->required
        ];
    }

    public static function validateSettings(Collection|array|null $settings): Collection
    {
        $validSettings = Collection::init();

        static::availableProperties()->each(function ($key, $inputType) use ($settings, $validSettings)
        {
            /**
             * @var Input $inputType
             */
            $settingValue = $settings->get($inputType->name);
            $defaultValue = static::defaultSettings()->get($inputType->name);

            if (in_array($inputType->name, $settings->keys()->unwrap()) && static::validByType($inputType->type, $settingValue)) {
                $validSettings->pushKeyValue($inputType->name, $settingValue);
            } else {
                $validSettings->pushKeyValue($inputType->name, $defaultValue);
            }
        });

        return $validSettings;
    }

    // Must defined default settings
    abstract public static function defaultSettings(): Collection;

    // Name + type + possible values (choices)
    abstract public static function availableProperties(): Collection;

    // To validate the field, return an error collection
    abstract public function validate(mixed $content): Collection;

    /**
     *
     * Valid a value by Field types
     *
     * @param  string  $type
     * @param  mixed   $value
     * @return bool
     *
     */
    protected static function validByType(string $type, mixed $value): bool
    {
        return match ($type) {
            Input::INPUT_TYPE_CHECKBOX => in_array($value, [true, false], true),
            Input::INPUT_TYPE_NUMBER => is_integer($value),
            default => false
        };
    }
}