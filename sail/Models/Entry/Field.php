<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\FieldException;
use SailCMS\Text;
use SailCMS\Types\Fields\Field as FieldType;
use SailCMS\Types\LocaleField;

abstract class Field extends Model
{
    /* ERRORS */
    const SCHEMA_MUST_CONTENT_FIELD_TYPE = 'The %s schema must contains only SailCMS/Types/Fields/Field type';

    /* ATTRIBUTES */
    public LocaleField $labels;
    public string $handle;
    public Collection $schema; // content FieldSchema type, minlength, required (base html field)

    public function __construct(string $collection = 'fields', int $dbIndex = 0)
    {
        parent::__construct($collection, $dbIndex);

        $this->init();
    }

    /**
     *
     * Fields of field
     *
     * @param  bool  $fetchAllFields
     * @return string[]
     *
     */
    public function fields(bool $fetchAllFields = false): array
    {
        return ['_id', 'labels', 'handle', 'schema'];
    }

    public function init(): void
    {
        $name = array_reverse(explode('\\', get_class($this)))[0];
        $this->handle = Text::snakeCase($name);

        $this->defineLabels();
        $this->defineSchema();
    }

    /**
     *
     * This is the content validation
     *
     * @param  Collection  $layoutSchema
     * @param  Collection  $content
     * @return Collection
     * @throws FieldException
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

        $this->validate($content);
    }

    // Must define the field labels
    abstract protected function defineLabels(): void;

    // Must define the field schema
    abstract protected function defineSchema(): void;

    // Throw exception when content is not valid
    abstract protected function validate(Collection $content): ?Collection;

    // TODO graphql type
}