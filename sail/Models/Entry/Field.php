<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\FieldInfo;
use SailCMS\Types\Fields\Field as InputField;
use SailCMS\Types\Fields\InputSettings;
use SailCMS\Types\LayoutField;
use SailCMS\Types\LocaleField;
use stdClass;

abstract class Field
{
    const SEARCHABLE = false;

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
     * @param  LocaleField            $labels
     * @param  Collection|array|null  $settings
     *
     */
    public function __construct(LocaleField $labels, Collection|array|null $settings = null)
    {
        $this->handle = str_replace('\\', '-', get_class($this));
        $this->labels = $labels;

        $this->defineBaseConfigs();
        $this->instantiateConfigs($labels, $settings);
    }

    /**
     *
     * Update schema attribute before save with settings
     *
     * @param  LocaleField            $labels
     * @param  Collection|array|null  $settings
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
            $defaultSettings = $this::class::defaultSettings()->get($key);
            $currentSetting = $fieldTypeClass::validateSettings($currentSetting, $defaultSettings);

            $fieldInput = new $fieldTypeClass($labels, ...$currentSetting);
            $this->configs->pushKeyValue($key, $fieldInput);
        });
    }

    public function isRequired(): bool
    {
        $result = false;

        foreach ($this->configs as $inputField) {
            /**
             * @var InputField $inputField
             */
            if ($inputField->required) {
                $result = true;
                break;
            }
        }
        return $result;
    }

    /**
     *
     * This is the default content validation for the field
     *
     * @param  mixed  $content
     * @return Collection
     *
     */
    public function validateContent(mixed $content): Collection
    {
        $errors = new Collection();

        if (is_array($content)) {
            $content = new Collection($content);
        }

        $this->configs->each(function ($index, $fieldTypeClass) use ($content, &$errors) {
            $currentContent = $content;
            if ($content instanceof Collection) {
                $currentContent = $content->get($index);
            }
            $error = $fieldTypeClass->validate($currentContent);

            if ($error->length > 0) {
                $errors->pushKeyValue($index, $error);
            }
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
     * @param  array|stdClass  $data
     * @return Field
     *
     */
    public static function fromLayoutField(array|stdClass $data): Field
    {
        $settings = [];
        $configsData = new Collection((array)$data->configs);

        $configsData->each(function ($key, $field) use (&$settings) {
            // FIX : for when and EntryLayout is from the cache.
            if (!isset($field->settings)) {
                $fieldSettings = (array)$field;
                unset($fieldSettings['labels']);
                $settings[$key] = $fieldSettings;
            } else {
                $settings[$key] = (array)$field->settings;
            }
        });

        $className = static::getClassFromHandle($data->handle);
        $labels = new LocaleField($data->labels ?? []);
        return new $className($labels, $settings);
    }

    /**
     *
     * Get the class name from the FieldLayout handle
     *
     * @param  string  $handle
     * @return string
     *
     */
    public static function getClassFromHandle(string $handle): string
    {
        return str_replace('-', '\\', $handle);
    }

    /**
     *
     * Return the field info
     *
     * @return FieldInfo
     *
     */
    public static function info(): FieldInfo
    {
        $fakeLabels = new LocaleField(['en' => 'Fake', 'fr' => 'Faux']);
        $fakeInstance = new static($fakeLabels, []);

        $availableSettings = Collection::init();
        $fakeInstance->baseConfigs->each(function ($i, $inputFieldClass) use (&$availableSettings) {
            /**
             * @var InputField $inputFieldClass
             */
            $settings = Collection::init();
            $inputFieldClass::availableProperties()->each(function ($i, $inputSettings) use (&$settings) {
                /**
                 * @var InputSettings $inputSettings
                 */
                $inputSettingsList = $inputSettings->castFrom();
                $inputSettingsList['value'] = "";
                $settings->push($inputSettingsList);
            });

            $className = array_reverse(explode('\\', $inputFieldClass))[0];
            $availableSettings->push([
                'name' => $className,
                'fullname' => (string)$inputFieldClass,
                'type' => $inputFieldClass::storingType(),
                'inputKey' => $i,
                'availableSettings' => $settings->unwrap()
            ]);
        });

        $className = array_reverse(explode('\\', static::class))[0];

        return new FieldInfo(
            $className,
            static::class,
            $fakeInstance->handle,
            $fakeInstance->description(),
            $fakeInstance->storingType(),
            static::SEARCHABLE,
            $availableSettings->unwrap()
        );
    }

    /**
     *
     * Parse the content for the Entry->getContent()
     *  can be overridden in child class to adapt according to the field content
     *
     * @param  mixed  $content
     * @return mixed
     *
     */
    public function parse(mixed $content): mixed
    {
        return $content;
    }

    /**
     *
     * Return the description of the field for the field info
     *
     * @return string
     *
     */
    abstract public function description(): string;

    /**
     *
     * The storing type in the database
     *
     * @return string
     *
     */
    abstract public function storingType(): string;

    /**
     *
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
     * The extra validation for the field
     *
     * @param  Collection  $content
     * @return Collection|null
     *
     */
    abstract protected function validate(Collection $content): ?Collection;
}