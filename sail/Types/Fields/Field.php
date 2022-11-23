<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Contracts\DatabaseType;
use SailCMS\Types\LocaleField;
use stdClass;

abstract class Field implements DatabaseType
{
    /* Errors */
    const INVALID_VALUE_FOR_TYPE = 'Invalid value %s for %s';

    /**
     *
     * Structure to replicate an html input
     *
     * @param LocaleField $labels
     * @param bool $required
     *
     */
    public function __construct(
        public readonly LocaleField $labels,
        public readonly bool        $required = false
    )
    {
    }

    /**
     *
     * For storing in the database
     *  > IMPORTANT : the settings must be regrouped in a configs array
     *
     * @return stdClass
     *
     */
    public function toDBObject(): stdClass
    {
        return (object)[
            'labels' => $this->labels->toDBObject(),
            'configs' => [
                'required' => $this->required
            ]
        ];
    }

    /**
     *
     * Validate settings before the schema creation in an entry layout
     *
     * @param Collection|array|null $settings
     * @return Collection
     *
     */
    public static function validateSettings(Collection|array|null $settings): Collection
    {
        $validSettings = Collection::init();

        static::availableProperties()->each(function ($key, $inputType) use ($settings, $validSettings) {
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

    /**
     *
     * Must defined default settings
     *
     * @return Collection
     *
     */
    abstract public static function defaultSettings(): Collection;

    /**
     *
     * Must define the available properties
     *
     * @return Collection
     *
     */
    abstract public static function availableProperties(): Collection;

    // To validate the field, return an error collection

    /**
     *
     * Validate the input from a given content
     *
     * @param mixed $content
     * @return Collection
     *
     */
    abstract public function validate(mixed $content): Collection;

    /**
     *
     * Valid a value by Field types
     *
     * @param string $type
     * @param mixed $value
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