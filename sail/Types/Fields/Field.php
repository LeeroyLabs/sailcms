<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Contracts\DatabaseType;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;
use stdClass;

abstract class Field implements DatabaseType
{
    /* Errors from 6100 to 6119 */
    public const FIELD_REQUIRED = "6100: This field is required.";

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

    public function __toString(): string
    {
        return $this::class;
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

    /**
     *
     * Get the type of how it's store in the database
     *
     * @return string
     */
    abstract public static function storingType(): string;

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
            InputSettings::INPUT_TYPE_CHECKBOX => in_array($value, [true, false], true),
            InputSettings::INPUT_TYPE_NUMBER => is_integer((int)$value),
            default => false
        };
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

        static::availableProperties()->each(function ($key, $inputType) use ($settings, &$validSettings) {
            /**
             * @var InputSettings $inputType
             */
            $settingValue = $settings?->get($inputType->name);
            $defaultValue = static::defaultSettings()->get($inputType->name);

            if ($settingValue && static::validByType($inputType->type, $settingValue)) {
                $validSettings->pushKeyValue($inputType->name, $settingValue);
            } else {
                $validSettings->pushKeyValue($inputType->name, $defaultValue);
            }
        });

        return $validSettings;
    }

    /**
     *
     * Get setting type from a field
     *
     * @param string $name
     * @param mixed $value
     * @return string
     *
     */
    public function getSettingType(string $name, mixed $value): string
    {
        $type = StoringType::STRING->value;
        static::availableProperties()->filter(function ($setting) use (&$type, $name, $value) {
            if ($setting->name === $name) {
                $type = match ($setting->type) {
                    "number" => is_float($value) ? StoringType::FLOAT->value : StoringType::INTEGER->value,
                    "checkbox" => StoringType::BOOLEAN->value,
                    default => StoringType::STRING->value
                };
            }
        });

        return $type;
    }

    /**
     *
     * For storing in the database
     *  > IMPORTANT : the settings must be regrouped in a 'settings' array
     *
     * @return stdClass
     *
     */
    public function toDBObject(): stdClass
    {
        return (object)[
            'labels' => $this->labels->toDBObject(),
            'settings' => [
                'required' => $this->required
            ]
        ];
    }
}