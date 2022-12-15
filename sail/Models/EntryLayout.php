<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\FieldException;
use SailCMS\Errors\PermissionException;
use SailCMS\Models\Entry\Field as ModelField;
use SailCMS\Text;
use SailCMS\Types\Authors;
use SailCMS\Types\Dates;
use SailCMS\Types\Fields\Field;
use SailCMS\Types\LayoutField;
use SailCMS\Types\LocaleField;
use stdClass;

class EntryLayout extends Model
{
    /* Errors */
    const DATABASE_ERROR = '6001: Exception when %s an entry.';
    const SCHEMA_MUST_CONTAIN_FIELDS = '6002: The schema must contains only SailCMS\Models\Entry\Field instances.';
    const SCHEMA_IS_USED = '6003: Cannot delete the schema because it is used by entry types.';
    const SCHEMA_KEY_ALREADY_EXISTS = '6004: Cannot use "%s" again, it is already in the schema.';
    const SCHEMA_KEY_DOES_NOT_EXISTS = '6005: The given key "%s" does not exists in the schema.';
    const DOES_NOT_EXISTS = '6006: Entry layout "%s" does not exists.';

    const ACL_HANDLE = "entrylayout";

    /* Properties */
    public LocaleField $titles;
    public Collection $schema;
    public Authors $authors;
    public Dates $dates;
    public bool $is_trashed;

    /**
     *
     * Initialize the entry
     *
     * @return void
     *
     */
    public function init(): void
    {
        $this->setPermissionGroup(static::ACL_HANDLE);
    }

    /**
     *
     * Layout available fields
     *
     * @param bool $fetchAllFields
     * @return string[]
     */
    public function fields(bool $fetchAllFields = false): array
    {
        return ['_id', 'titles', 'schema', 'authors', 'dates', 'is_trashed'];
    }

    /**
     *
     * Parse the entry into an array for api
     *
     * @return array
     *
     */
    public function toGraphQL(): array
    {
        return [
            '_id' => $this->_id,
            'titles' => $this->titles->toDBObject(),
            'schema' => $this->processSchemaToGraphQL(),
            'authors' => $this->authors->toDBObject(),
            'dates' => $this->dates->toDBObject(),
            'is_trashed' => $this->is_trashed
        ];
    }

    /**
     *
     * Generate a schema for a layout for list of given fields
     *
     * @param Collection $fields
     * @return Collection
     * @throws FieldException
     *
     */
    public static function generateLayoutSchema(Collection $fields): Collection
    {
        $schema = Collection::init();
        $fields->each(function ($key, $field) use ($schema) {
            if (!$field instanceof ModelField) {
                throw new FieldException(static::SCHEMA_MUST_CONTAIN_FIELDS);
            }

            $schema->pushKeyValue(Text::slugify($key), $field->toLayoutField());
        });

        return $schema;
    }

    /**
     *
     * Get all entry layouts
     *
     * @param bool $ignoreTrashed default true
     * @return array|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function getAll(bool $ignoreTrashed = true): ?array
    {
        $this->hasPermissions(true);

        $filters = [];
        if ($ignoreTrashed) {
            $filters = ['is_trashed' => false];
        }

        return $this->find($filters)->exec();
    }

    /**
     *
     * Find one user with filters
     *
     * @param array $filters
     * @return EntryLayout|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     */
    public function one(array $filters): ?EntryLayout
    {
        $this->hasPermissions(true);

        if (isset($filters['_id'])) {
            return $this->findById($filters['_id'])->exec();
        }
        return $this->findOne($filters)->exec();
    }

    /**
     *
     * Create an entry layout with
     *
     * @param LocaleField $titles
     * @param Collection $schema
     * @return EntryLayout
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function create(LocaleField $titles, Collection $schema): EntryLayout
    {
        $this->hasPermissions();

        // Schema preparation
        static::validateSchema($schema);
        $schema = $this->processSchemaOnStore($schema);

        return $this->createWithoutPermission($titles, $schema);
    }

    /**
     *
     * Update the current schema with a field key, an array of keys and values and a field index
     * > The field key is generated at the entry layout creation with the Models\Entry\Field->generateKey method
     * > The to update array keys must be property of the Input type of the field index, if not there will be an error
     * > The field index is the index according to the baseConfigs of a Models\Entry\Field class,
     *      ¬ normally the Field class has only one element, but more complex ones can have many fields
     *
     * @param string $fieldKey
     * @param array $toUpdate
     * @param int $fieldIndex
     * @param LocaleField|null $labels
     * @return void
     *
     */
    public function updateSchemaConfig(string $fieldKey, array $toUpdate, int $fieldIndex = 0, ?LocaleField $labels = null): void
    {
        $this->schema->each(function ($currentFieldKey, &$field) use ($fieldKey, $toUpdate, $fieldIndex, $labels) {
            /**
             * @var ModelField $field
             */
            if ($currentFieldKey === $fieldKey) {
                $currentInput = $field->configs->get($fieldIndex)->toDbObject();
                $inputClass = $field->configs->get($fieldIndex)::class;

                foreach ($toUpdate as $key => $value) {
                    $currentInput->settings[$key] = $value;
                }

                $newLabels = $labels ?? new LocaleField($currentInput->labels);

                $input = new $inputClass($newLabels, ...$currentInput->settings);
                $field->configs->pushKeyValue('0', $input);

                $this->schema->pushKeyValue($currentFieldKey, new LayoutField($newLabels, $field->handle, $field->configs));
            }
        });
    }

    /**
     *
     * Update an entry layout for a given id or entryLayout instance
     *
     * @param Entry|string $entryOrId
     * @param LocaleField|null $titles
     * @param Collection|null $schema
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     */
    public function updateById(Entry|string $entryOrId, ?LocaleField $titles, ?Collection $schema): bool
    {
        $this->hasPermissions();

        if (is_string($entryOrId)) {
            $entry = $this->findById($entryOrId)->exec();
        } else {
            $entry = $entryOrId;
        }

        $data = Collection::init();

        if ($titles) {
            $data->pushKeyValue('titles', $titles);
        }

        if ($schema) {
            static::validateSchema($schema);
            $schema = $this->processSchemaOnStore($schema);
            $data->pushKeyValue('schema', $schema);
        }

        if ($data->length > 0) {
            return $this->updateWithoutPermission($entry, $data);
        }
        return false;
    }

    /**
     *
     * Update a key in the schema
     *
     * @param string $key
     * @param string $newKey
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function updateSchemaKey(string $key, string $newKey): bool
    {
        $this->hasPermissions();

        // Slugify the new key
        $newKey = Text::slugify($newKey);

        if (!in_array($key, $this->schema->keys()->unwrap())) {
            throw new EntryException(sprintf(static::SCHEMA_KEY_DOES_NOT_EXISTS, $key));
        }

        if (in_array($newKey, $this->schema->keys()->unwrap())) {
            throw new EntryException(sprintf(static::SCHEMA_KEY_ALREADY_EXISTS, $newKey));
        }

        $newSchema = Collection::init();
        $this->schema->each(function ($currentKey, $modelField) use (&$newSchema, $key, $newKey) {
            /**
             * @var ModelField $modelField
             */
            if ($key === $currentKey) {
                $currentKey = $newKey;
            }
            $newSchema->pushKeyValue($currentKey, $modelField->toLayoutField());
        });

        $result = $this->updateById($this->_id, null, $newSchema);

        if ($result) {
            $entryTypes = EntryType::findAll([
                'entry_layout_id' => (string)$this->_id
            ]);

            $entryTypes->each(function ($k, $entryType) use ($key, $newKey) {
                /**
                 * @var EntryType $entryType
                 */
                $entryModel = $entryType->getEntryModel();
                $entryModel->updateAllContentKey($key, $newKey);
            });
        }

        return $result;
    }

    /**
     *
     * Delete an entry layout with soft or hard mode
     *
     * @param string|ObjectId $entryLayoutId
     * @param bool $soft
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function delete(string|ObjectId $entryLayoutId, bool $soft = true): bool
    {
        $this->hasPermissions();

        // Check if there is and entry type is using the layout
        if ($this->hasEntryTypes($entryLayoutId)) {
            throw new EntryException(static::SCHEMA_IS_USED);
        }

        if ($soft) {
            $entryLayout = $this->findById($entryLayoutId)->exec();

            if (!$entryLayout) {
                throw new EntryException(sprintf(static::DOES_NOT_EXISTS, $entryLayoutId));
            }

            $result = $this->softDelete($entryLayout);
        } else {
            $result = $this->hardDelete($entryLayoutId);
        }

        return $result;
    }

    /**
     *
     * Process schema from GraphQL inputs
     *
     * @param array|Collection $configs
     * @return Collection
     * @throws EntryException
     *
     */
    public static function processSchemaFromGraphQL(array|Collection $configs): Collection
    {
        $schema = new Collection();

        if (is_array($configs)) {
            $configs = new Collection($configs);
        }

        $keys = Collection::init();
        foreach ($configs as $fieldSettings) {
            $fieldClass = ModelField::getClassFromHandle($fieldSettings->handle);
            $labels = new LocaleField($fieldSettings->labels->unwrap());

            if (!$keys->has($fieldSettings->key)) {
                $keys->push($fieldSettings->key);
            } else {
                throw new EntryException(sprintf(static::SCHEMA_KEY_ALREADY_EXISTS, $fieldSettings->key));
            }

            $parsedConfigs = Collection::init();
            $fieldSettings->inputSettings->each(function ($index, $fields) use (&$parsedConfigs) {
                $settings = Collection::init();
                $fields->each(function ($key, $setting) use (&$settings) {
                    $settings->pushKeyValue($setting->name, EntryLayout::parseSettingValue($setting->type, $setting->value));
                });

                $parsedConfigs->pushKeyValue($index, $settings);
            });

            $field = new $fieldClass($labels, $parsedConfigs);
            $schema->pushKeyValue($fieldSettings->key, $field);
        }

        return $schema;
    }

    /**
     *
     * Update a schema from graphQL inputs
     *
     * @param Collection $schemaUpdate
     * @param EntryLayout $entryLayout
     * @return void
     *
     */
    public static function updateSchemaFromGraphQL(Collection $schemaUpdate, EntryLayout $entryLayout)
    {
        $schemaUpdate->each(function ($key, $updateInput) use (&$entryLayout) {
            if (isset($updateInput->inputSettings)) {
                $updateInput->inputSettings->each(function ($index, $toUpdate) use ($entryLayout, $updateInput) {
                    $settings = [];
                    $toUpdate->each(function ($k, $setting) use (&$settings) {
                        $settings[$setting->name] = EntryLayout::parseSettingValue($setting->type, $setting->value);
                    });

                    $labels = null;
                    if (isset($updateInput->labels)) {
                        $labels = new LocaleField($updateInput->labels->unwrap());
                    }
                    $entryLayout->updateSchemaConfig($updateInput->get('key'), $settings, $index, $labels);
                });
            } else if (isset($updateInput->labels)) {
                $labels = new LocaleField($updateInput->labels->unwrap());
                $entryLayout->updateSchemaConfig($updateInput->get('key'), [], 0, $labels);
            }
        });
    }

    /**
     *
     * Process schema to GraphQL inputs
     *
     * @return Collection
     *
     */
    public function processSchemaToGraphQL(): Collection
    {
        $apiSchema = Collection::init();

        $this->schema->each(function ($fieldKey, $layoutField) use ($apiSchema) {
            $layoutFieldConfigs = Collection::init();
            $layoutFieldSettings = Collection::init();

            $layoutField->configs->each(function ($fieldIndex, $input) use ($layoutFieldConfigs, $layoutFieldSettings) {
                /**
                 * @var Field $input
                 */
                $settings = new Collection($input->toDBObject()->settings);
                $fieldSettings = Collection::init();

                $settings->each(function ($name, $value) use ($fieldSettings, $input) {
                    if (is_bool($value)) {
                        $value = $value ? 'true' : 'false';
                    }

                    $type = $input->getSettingType($name, $value);

                    $fieldSettings->push([
                        'name' => $name,
                        'value' => (string)$value,
                        'choices' => [],
                        'type' => $type
                    ]);
                });

                $layoutFieldSettings->push($fieldSettings);
            });
            $layoutFieldConfigs->push([
                'handle' => $layoutField->handle,
                'labels' => $layoutField->labels->toDBObject(),
                'inputSettings' => $layoutFieldSettings->unwrap()
            ]);

            $apiSchema->push([
                'key' => $fieldKey,
                'fieldConfigs' => $layoutFieldConfigs->unwrap()
            ]);
        });

        return $apiSchema;
    }

    /**
     *
     * Fields process for fetching
     *
     * @param string $field
     * @param mixed $value
     * @return mixed
     *
     */
    protected function processOnFetch(string $field, mixed $value): mixed
    {
        return match ($field) {
            'titles' => new LocaleField($value),
            'schema' => $this->processSchemaOnFetch($value),
            'authors' => new Authors($value->created_by, $value->updated_by, $value->published_by, $value->deleted_by),
            'dates' => new Dates($value->created, $value->updated, $value->published, $value->deleted),
            default => $value,
        };
    }

    /**
     *
     * Parse an input setting value according the given type
     *
     * @param string $type
     * @param string $value
     * @return string
     *
     */
    private static function parseSettingValue(string $type, string $value): string
    {
        if ($type == "boolean") {
            $result = !($value === "false");
        } else if ($type == "integer") {
            $result = (integer)$value;
        } else if ($type == "float") {
            $result = (float)$value;
        } else {
            $result = $value;
        }
        return $result;
    }

    /**
     *
     * Validate the schema before save
     *
     * @param Collection $schema
     * @return void
     * @throws EntryException
     *
     */
    private static function validateSchema(Collection $schema)
    {
        $schema->each(function ($key, $value) {
            if (!$value instanceof LayoutField) {
                throw new EntryException('TODO');
            }
        });
    }

    /**
     *
     * Process schema on fetch
     *
     * @param stdClass|array|null $value
     * @return Collection
     *
     */
    private function processSchemaOnFetch(stdClass|array|null $value): Collection
    {
        if (!$value) {
            return Collection::init();
        }

        $schema = Collection::init();
        $schemaFromDb = new Collection((array)$value);

        $schemaFromDb->each(function ($key, $value) use (&$schema) {
            $valueParsed = ModelField::fromLayoutField($value);
            $schema->pushKeyValue($key, $valueParsed);
        });

        return $schema;
    }

    /**
     *
     * Process schema on store AKA convert all type to db object
     *
     * @param Collection $schema
     * @return Collection
     *
     */
    private function processSchemaOnStore(Collection &$schema): Collection
    {
        $schemaForDb = Collection::init();
        $schema->each(function ($fieldId, $layoutField) use ($schemaForDb) {
            /**
             * @var LayoutField $layoutField
             */
            $layoutField = $layoutField->toDBObject();
            $schemaForDb->pushKeyValue($fieldId, $layoutField);
        });
        return $schemaForDb;
    }

    /**
     *
     * Check if an entry layout have a related entry type
     *
     * @param string|ObjectId $entryLayoutId
     * @return bool
     *
     */
    public static function hasEntryTypes(string|ObjectId $entryLayoutId): bool
    {
        $entryTypeCount = (new EntryType())->count(['entry_layout_id' => (string)$entryLayoutId]);

        return $entryTypeCount > 0;
    }

    /**
     *
     * Create an entry layout
     *
     * @param LocaleField $titles
     * @param Collection $schema
     * @return EntryLayout
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    private function createWithoutPermission(LocaleField $titles, Collection $schema): EntryLayout
    {
        $dates = Dates::init();
        $authors = Authors::init(User::$currentUser, false);

        try {
            $entryLayoutId = $this->insert([
                'titles' => $titles,
                'schema' => $schema,
                'authors' => $authors,
                'dates' => $dates,
                'is_trashed' => false
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(static::DATABASE_ERROR, 'creating') . PHP_EOL . $exception->getMessage());
        }

        return $this->findById($entryLayoutId)->exec();
    }

    /**
     *
     * Update an entry layout
     *
     * @param EntryLayout $entryLayout
     * @param Collection $data
     * @return bool
     * @throws EntryException
     *
     */
    private function updateWithoutPermission(EntryLayout $entryLayout, Collection $data): bool
    {
        $update = [
            'dates' => Dates::updated($entryLayout->dates),
            'authors' => Authors::updated($entryLayout->authors, User::$currentUser->_id)
        ];

        $titles = $data->get('titles');
        if ($titles) {
            $update['titles'] = $titles;
        }

        $schema = $data->get('schema');
        if ($schema) {
            $update['schema'] = $schema;
        }

        try {
            $qtyUpdated = $this->updateOne(['_id' => $entryLayout->_id], [
                '$set' => $update
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(static::DATABASE_ERROR, 'updating') . PHP_EOL . $exception->getMessage());
        }

        return $qtyUpdated === 1;
    }

    /**
     *
     * Delete an entry layout to the trash
     *
     * @param EntryLayout $entryLayout
     * @return bool
     * @throws EntryException
     *
     */
    private function softDelete(EntryLayout $entryLayout): bool
    {
        $authors = Authors::deleted($entryLayout->authors, User::$currentUser->_id);
        $dates = Dates::deleted($entryLayout->dates);

        try {
            $qtyUpdated = $this->updateOne(['_id' => $entryLayout->_id], [
                '$set' => [
                    'authors' => $authors,
                    'dates' => $dates,
                    'is_trashed' => true
                ]
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(static::DATABASE_ERROR, 'soft deleting') . PHP_EOL . $exception->getMessage());
        }

        return $qtyUpdated === 1;
    }

    /**
     *
     * Delete an entry layout forever
     *
     * @param string|ObjectId $entryLayoutId
     * @return bool
     * @throws EntryException
     *
     */
    private function hardDelete(string|ObjectId $entryLayoutId): bool
    {
        try {
            $qtyDeleted = $this->deleteById((string)$entryLayoutId);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(static::DATABASE_ERROR, 'hard deleting') . PHP_EOL . $exception->getMessage());
        }

        return $qtyDeleted === 1;
    }
}