<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Errors\FieldException;
use SailCMS\Text;
use SailCMS\Types\Fields\Field as FieldType;
use SailCMS\Types\LayoutField;
use SailCMS\Types\LocaleField;
use stdClass;


abstract class Field
{
    /* Errors */
    const SCHEMA_MUST_CONTENT_FIELD_TYPE = 'The %s schema must contains only SailCMS/Types/Fields/Field type';

    /* Properties */
    public LocaleField $labels;
    public string $handle;
    public Collection $baseConfigs;
    public Collection $configs;

    /**
     *
     * Construct with a LocaleField for labels and a Collection|Array for settings
     *  > If settings is null the default settings will be used
     *
     * @param LocaleField $labels
     * @param Collection|array|null $settings
     *
     */
    public function __construct(LocaleField $labels, Collection|array|null $settings)
    {
        $name = array_reverse(explode('\\', get_class($this)))[0];
        $this->handle = Text::snakeCase($name);
        $this->labels = $labels;

        $this->defineBaseConfigs();
        $this->instantiateConfigs($labels, $settings);
    }

    /**
     *
     *  Generate a field to retrieve a field in an entry layout or in an entry content
     *
     * @return string
     *
     */
    public function generateKey(): string
    {
        return $this->handle . '_' . uniqid();
    }

    /**
     *
     * Update schema attribute before save with settings
     *
     * @param LocaleField $labels
     * @param Collection|array|null $settings
     * @return void
     *
     */
    public function instantiateConfigs(LocaleField $labels, Collection|array|null $settings): void
    {
        // Parse configs according to his type
        $this->configs = Collection::init();
        $settings = !$settings ? $this->defaultSettings() : $settings;
        if (is_array($settings)) {
            $settings = new Collection($settings);
        }

        $this->baseConfigs->each(function ($key, $fieldTypeClass) use ($labels, $settings) {
            $currentSetting = $settings->get($key);
            $currentSetting = $fieldTypeClass::validateSettings($currentSetting);

            $fieldInput = new $fieldTypeClass($labels, ...$currentSetting);
            $this->configs->push($fieldInput);
        });
    }

    /**
     *
     * This is the default content validation for the field
     *
     * @param Collection $layoutSchema
     * @param Collection $content
     * @return Collection
     * @throws FieldException
     *
     */
    public function validateContent(Collection $layoutSchema, Collection $content): Collection
    {
        $errors = new Collection();
        $layoutSchema->each(function ($key, $value) use ($content, &$errors) {
            $fieldTypeInstance = new $value();
            if (!$fieldTypeInstance instanceof FieldType) {
                $className = array_reverse(explode('\\', get_class($this)))[0];
                throw new FieldException(sprintf(static::SCHEMA_MUST_CONTENT_FIELD_TYPE, $className));
            }

            $errors = $fieldTypeInstance->validate($content->get($key));
        });

        $otherErrors = $this->validate($content);

        if ($otherErrors) {
            $errors->pushSpread(...$otherErrors);
        }

        return $errors;
    }

    /**
     *
     * Return a Layout Field to store in an entry layout schema
     *
     * @return LayoutField
     *
     */
    public function toLayoutField(): LayoutField
    {
        return new LayoutField($this->labels, $this->handle, $this->configs);
    }

    /**
     *
     * Retrieve a field from a layout field that comes from the entry layout schema stored in the database
     *
     * @param array|stdClass $data
     * @return Field
     *
     */
    public static function fromLayoutField(array|stdClass $data): Field
    {
        $settings = [];
        $configsData = new Collection($data->configs);

        $configsData->each(function ($key, $field) use (&$settings) {
            $settings[$key] = (array)$field->settings;
        });

        $className = static::getClassFromHandle($data->handle);

        $labels = new LocaleField($data->labels ?? []);

        return new $className($labels, $settings);
    }

    /**
     *
     * Get the class name from the FieldLayout handle
     *
     * @param string $handle
     * @return string
     *
     */
    public static function getClassFromHandle(string $handle): string
    {
        $handle = explode('_', $handle);
        $className = __NAMESPACE__ . '\\';

        foreach ($handle as $key => $value) {
            $className .= ucfirst($value);
        }

        return $className;
    }

    /**
     * Must define default settings of the field
     *
     * @return Collection
     *
     */
    abstract public function defaultSettings(): Collection;

    /**
     *
     * Must define the field base schema, the input in the fields
     *
     * @return void
     *
     */
    abstract protected function defineBaseConfigs(): void;

    /**
     *
     * THe extra validation for the field
     *
     * @param Collection $content
     * @return Collection|null
     *
     */
    abstract protected function validate(Collection $content): ?Collection;
}