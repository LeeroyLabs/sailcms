<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Errors\FieldException;
use SailCMS\Text;
use SailCMS\Types\Fields\Field as FieldType;
use SailCMS\Types\Fields\LayoutField;
use SailCMS\Types\LocaleField;
use stdClass;


abstract class Field
{
    /* ERRORS */
    const SCHEMA_MUST_CONTENT_FIELD_TYPE = 'The %s schema must contains only SailCMS/Types/Fields/Field type';

    public string $handle;
    public Collection $baseConfigs;
    public Collection $configs;

    public function __construct(LocaleField $labels, Collection|array|null $settings)
    {
        $name = array_reverse(explode('\\', get_class($this)))[0];
        $this->handle = Text::snakeCase($name);

        $this->defineBaseConfigs();
        $this->instantiateConfigs($labels, $settings);
    }

    public function generateKey(): string
    {
        return $this->handle . '_' . uniqid();
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

        $this->baseConfigs->each(function ($key, $fieldTypeClass) use ($labels, $settings)
        {
            $currentSetting = $settings->get($key);
            $currentSetting = $fieldTypeClass::validateSettings($currentSetting);

            $fieldInput = new $fieldTypeClass($labels, ...$currentSetting);
            $this->configs->push($fieldInput);
        });
    }

    /**
     *
     * This is the content validation
     *
     * @param  Collection  $layoutSchema
     * @param  Collection  $content
     * @return Collection
     * @throws FieldException
     *
     */
    public function validateContent(Collection $layoutSchema, Collection $content): Collection
    {
        $errors = new Collection();
        $layoutSchema->each(function ($key, $value) use ($content, &$errors)
        {
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
     * @return LayoutField
     */
    public function toLayoutField(): LayoutField
    {
        return new LayoutField($this->handle, $this->configs);
    }

    public static function fromLayoutField(array|stdClass $data): Field
    {
        print_r($data);
    }

    // Must define default settings
    abstract public function defaultSettings(): Collection;

    // Must define the field schema
    abstract protected function defineBaseConfigs(): void;

    // Throw exception when content is not valid
    abstract protected function validate(Collection $content): ?Collection;

    // TODO graphql type
}