<?php

namespace SailCMS\Models;

use JsonException;
use MongoDB\BSON\ObjectId;
use SailCMS\Cache;
use SailCMS\Collection;
use SailCMS\Contracts\Castable;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\FieldException;
use SailCMS\Errors\PermissionException;
use SailCMS\Locale;
use SailCMS\Models\Entry\Field as ModelField;
use SailCMS\Text;
use SailCMS\Types\Authors;
use SailCMS\Types\Dates;
use SailCMS\Types\Fields\Field;
use SailCMS\Types\LayoutField;
use SailCMS\Types\LocaleField;
use stdClass;

/**
 *
 *
 * @property string           $slug
 * @property LocaleField      $titles
 * @property array|Collection $schema
 * @property Authors          $authors
 * @property Dates            $dates
 * @property bool             $is_trashed
 *
 */
class EntryLayout extends Model implements Castable
{
    protected string $collection = 'entry_layouts';
    protected string $permissionGroup = 'entrylayout';
    protected array $casting = [
        'titles' => LocaleField::class,
        'schema' => self::class,
        'authors' => Authors::class,
        'dates' => Dates::class
    ];

    /* Errors */
    public const DATABASE_ERROR = '6001: Exception when %s an entry.';
    public const SCHEMA_MUST_CONTAIN_FIELDS = '6002: The schema must contains only SailCMS\Models\Entry\Field instances.';
    public const SCHEMA_IS_USED = '6003: Cannot delete the schema because it is used by entry types.';
    public const SCHEMA_KEY_ALREADY_EXISTS = '6004: Cannot use "%s" again, it is already in the schema.';
    public const SCHEMA_KEY_DOES_NOT_EXISTS = '6005: The given key "%s" does not exists in the schema.';
    public const DOES_NOT_EXISTS = '6006: Entry layout "%s" does not exists.';
    public const INVALID_SCHEMA = '6007: Invalid schema structure.';

    /* Cache */
    private const ENTRY_LAYOUT_CACHE_ALL = 'all_entry_layout';
    private const ENTRY_LAYOUT_BY_SLUG = 'entry_layout_';
    private const ENTRY_LAYOUT_ID_ = 'entry_layout_id_';

    /**
     *
     * Simplify schema
     *
     * @return array
     *
     */
    public function castFrom(): array
    {
        return $this->schema->unwrap();
    }

    /**
     *
     * Process Schema
     *
     * @param  mixed  $value
     * @return Collection
     *
     */
    public function castTo(mixed $value): Collection
    {
        return $this->processSchemaOnFetch($value);
    }

    /**
     *
     * Generate slug to be unique
     *
     * @param  string       $slug
     * @param  string|null  $entryLayoutId
     * @return string
     *
     */
    private static function generateSlug(string $slug, string $entryLayoutId = null): string
    {
        $filters = ['slug' => $slug];
        if ($entryLayoutId) {
            $filters['_id'] = ['$ne' => new ObjectId($entryLayoutId)];
        }

        $count = (new EntryLayout())->count($filters);

        if ($count > 0) {
            preg_match("/(?<base>[\w-]+-)(?<increment>\d+)$/", $slug, $matches);

            if (count($matches) > 0) {
                $increment = (int)$matches['increment'];
                $newSlug = $matches['base'] . ($increment + 1);
            } else {
                $newSlug = $slug . "-2";
            }

            return self::generateSlug($newSlug, $entryLayoutId);
        }
        return $slug;
    }

    /**
     *
     * Parse the entry into an array for api
     *
     * @return array
     *
     */
    public function simplify(): array
    {
        return [
            '_id' => (string)$this->_id,
            'slug' => $this->slug,
            'titles' => $this->titles->castFrom(),
            'schema' => $this->simplifySchema(),
            'authors' => $this->authors->castFrom(),
            'dates' => $this->dates->castFrom(),
            'is_trashed' => $this->is_trashed
        ];
    }

    /**
     *
     * Generate a schema for a layout for list of given fields
     *
     * @param  Collection  $fields
     * @return Collection
     * @throws FieldException
     *
     */
    public static function generateLayoutSchema(Collection $fields): Collection
    {
        $schema = Collection::init();
        $fields->each(function ($key, $field) use ($schema) {
            if (!$field instanceof ModelField) {
                throw new FieldException(self::SCHEMA_MUST_CONTAIN_FIELDS);
            }

            $schema->pushKeyValue($key, $field->toLayoutField());
        });

        return $schema;
    }

    /**
     *
     * Get all entry layouts
     *
     * @param  bool  $ignoreTrashed  default true
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

        // Cache Time To Live value from setting or default
        $cacheTtl = setting('entry.cacheTtl', Cache::TTL_WEEK);

        return $this->find($filters)->exec(self::ENTRY_LAYOUT_CACHE_ALL, $cacheTtl);
    }

    /**
     *
     * Get entry layout by slug.
     *
     * @param  string  $slug
     * @return EntryLayout|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function bySlug(string $slug): ?EntryLayout
    {
        $this->hasPermissions(true);

        // Cache Time To Live value from setting or default
        $cacheTtl = setting('entry.cacheTtl', Cache::TTL_WEEK);
        $cacheKey = self::ENTRY_LAYOUT_BY_SLUG . $slug;

        return $this->findOne(['slug' => $slug])->exec($cacheKey, $cacheTtl);
    }

    /**
     *
     * Find one user with filters
     *
     * @param  array  $filters
     * @param  bool   $cache
     * @return EntryLayout|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function one(array $filters, bool $cache = true): ?EntryLayout
    {
        $this->hasPermissions(true);

        if (isset($filters['_id'])) {
            if (!$cache) {
                return $this->findById($filters['_id'])->exec();
            }

            // Cache Time To Live value from setting or default
            $cacheTtl = setting('entry.cacheTtl', Cache::TTL_WEEK);
            $cacheKey = self::ENTRY_LAYOUT_ID_ . $filters['_id'];
            return $this->findById($filters['_id'])->exec($cacheKey, $cacheTtl);
        }
        return $this->findOne($filters)->exec();
    }

    /**
     *
     * Create an entry layout
     *
     * @param  LocaleField  $titles
     * @param  Collection   $schema
     * @param  string|null  $slug  slug is set to $title->{Locale::default()}
     * @return EntryLayout
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function create(LocaleField $titles, Collection $schema, ?string $slug = null): EntryLayout
    {
        $this->hasPermissions();

        // Schema preparation
        self::validateSchema($schema);
        $schema = $this->processSchemaOnStore($schema);

        return $this->createWithoutPermission($titles, $schema, $slug);
    }

    /**
     *
     * Update the current schema with a field key, an array of keys and values and a field index
     * > The field key is generated at the entry layout creation with the Models\Entry\Field->generateKey method
     * > The to update array keys must be property of the Input type of the field index, if not there will be an error
     * > The field index is the index according to the baseConfigs of a Models\Entry\Field class,
     *      ¬ normally the Field class has only one element, but more complex ones can have many fields
     *
     * @param  string            $fieldKey
     * @param  array             $toUpdate
     * @param  int|string        $fieldIndexName
     * @param  LocaleField|null  $labels
     * @return void
     *
     */
    public function updateSchemaConfig(string $fieldKey, array $toUpdate, int|string $fieldIndexName = 0, ?LocaleField $labels = null, ?bool $repeater = null): void
    {
        $this->schema->each(function ($currentFieldKey, $field) use ($fieldKey, $toUpdate, $fieldIndexName, $labels, $repeater) {
            /**
             * @var ModelField $field
             */
            if ($currentFieldKey === $fieldKey && $field->configs->get((string)$fieldIndexName)) {
                $currentInput = $field->configs->get((string)$fieldIndexName)->castFrom();
                $inputClass = $field->configs->get((string)$fieldIndexName)::class;

                foreach ($toUpdate as $key => $value) {
                    $currentInput->settings[$key] = $value;
                }

                $newLabels = $labels ?? new LocaleField($currentInput->labels);

                $input = new $inputClass($newLabels, ...$currentInput->settings);
                $field->configs->pushKeyValue($fieldIndexName, $input);

                $repeaterValue = $repeater !== null ? $repeater : $field->repeater;

                $this->schema->pushKeyValue($currentFieldKey, new LayoutField($newLabels, $field->handle, $field->configs, $repeaterValue));
            } else {
                $this->schema->pushKeyValue($currentFieldKey, $field->toLayoutField());
            }
        });
    }

    /**
     *
     * Update an entry layout for a given id or entryLayout instance
     *
     * @param  Entry|string      $entryOrId
     * @param  LocaleField|null  $titles
     * @param  Collection|null   $schema
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
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
            self::validateSchema($schema);
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
     * @param  string  $key
     * @param  string  $newKey
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     * @throws JsonException
     *
     */
    public function updateSchemaKey(string $key, string $newKey): bool
    {
        $this->hasPermissions();

        if (!in_array($key, $this->schema->keys()->unwrap(), true)) {
            throw new EntryException(sprintf(self::SCHEMA_KEY_DOES_NOT_EXISTS, $key));
        }

        if (in_array($newKey, $this->schema->keys()->unwrap(), true)) {
            throw new EntryException(sprintf(self::SCHEMA_KEY_ALREADY_EXISTS, $newKey));
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
     * @param  string|ObjectId  $entryLayoutId
     * @param  bool             $soft
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
        if (self::hasEntryTypes($entryLayoutId)) {
            throw new EntryException(self::SCHEMA_IS_USED);
        }

        if ($soft) {
            $entryLayout = $this->findById($entryLayoutId)->exec();

            if (!$entryLayout) {
                throw new EntryException(sprintf(self::DOES_NOT_EXISTS, $entryLayoutId));
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
     * @param  array|Collection  $configs
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
                throw new EntryException(sprintf(self::SCHEMA_KEY_ALREADY_EXISTS, $fieldSettings->key));
            }

            $parsedConfigs = Collection::init();
            $fieldSettings->inputSettings->each(function ($index, $fieldsData) use (&$parsedConfigs) {
                $settings = Collection::init();
                $fieldsData->settings->each(function ($key, $setting) use (&$settings) {
                    $options = EntryLayout::parseOptions($setting->options ?? null);
                    $settings->pushKeyValue($setting->name, EntryLayout::parseSettingValue($setting->type, $setting->value, $options));
                });
                $inputKey = $fieldsData->inputKey ?? $index;

                $parsedConfigs->pushKeyValue($inputKey, $settings);
            });

            /**
             * @var ModelField $field
             */
            $field = new $fieldClass($labels, $parsedConfigs, $fieldSettings->repeater ?? false);
            $schema->pushKeyValue($fieldSettings->key, $field);
        }

        return $schema;
    }

    /**
     *
     * Update a schema from graphQL inputs
     *
     * @param  Collection   $schemaUpdate
     * @param  EntryLayout  $entryLayout
     * @return void
     *
     */
    public static function updateSchemaFromGraphQL(Collection $schemaUpdate, EntryLayout $entryLayout): void
    {
        $schemaUpdate->each(function ($key, $updateInput) use (&$entryLayout) {
            if (isset($updateInput->inputSettings)) {
                /**
                 * @var object $updateInput
                 */
                $updateInput->inputSettings->each(function ($index, $toUpdate) use ($entryLayout, $updateInput) {
                    $settings = [];

                    $inputKey = $toUpdate->inputKey ?? $index;

                    $toUpdate->settings->each(function ($k, $setting) use (&$settings) {
                        $settings[$setting->name] = EntryLayout::parseSettingValue($setting->type, $setting->value);
                    });

                    $labels = null;
                    if (isset($updateInput->labels)) {
                        $labels = new LocaleField($updateInput->labels->unwrap());
                    }
                    $entryLayout->updateSchemaConfig($updateInput->key, $settings, $inputKey, $labels);
                });
            } elseif (isset($updateInput->labels)) {
                /**
                 * @var object $updateInput
                 */
                $labels = new LocaleField($updateInput->labels->unwrap());
                $entryLayout->updateSchemaConfig($updateInput->key, [], 0, $labels);
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
    public function simplifySchema(): Collection
    {
        $apiSchema = Collection::init();

        if (is_array($this->schema)) {
            $this->schema = new Collection($this->schema);
        }

        $this->schema->each(function ($fieldKey, $layoutField) use ($apiSchema) {
            $layoutFieldConfigs = Collection::init();
            $layoutFieldSettings = Collection::init();

            $layoutField->configs->each(function ($fieldIndex, $input) use (&$layoutFieldSettings) {
                /**
                 * @var Field $input
                 */
                $settings = new Collection($input->castFrom()->settings);
                $fieldSettings = Collection::init();

                $settings->each(function ($name, $value) use ($fieldSettings, $input) {
                    if (is_bool($value)) {
                        $value = $value ? 'true' : 'false';
                    }

                    $type = $input->getSettingType($name, $value);
                    $options = [];

                    if ($type === "array") {
                        $options = $value;
                        $value = "";
                    }
                    $fieldSettings->push([
                        'name' => $name,
                        'value' => (string)$value,
                        'options' => $options,
                        'type' => $type
                    ]);
                });

                $layoutFieldSettings->push([
                    'inputKey' => $fieldIndex,
                    'settings' => $fieldSettings
                ]);
            });
            $layoutFieldConfigs->push([
                'handle' => $layoutField->handle,
                'labels' => $layoutField->labels->castFrom(),
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
     * Parse an input setting value according the given type
     *
     * @param  string           $type
     * @param  string           $value
     * @param  Collection|null  $options
     * @return string|Collection
     *
     */
    private static function parseSettingValue(string $type, string $value, Collection $options = null): string|Collection
    {
        if ($type === "boolean") {
            $result = !($value === "false");
        } elseif ($type === "array") {
            $result = $options;
        } elseif ($type === "integer") {
            $result = (integer)$value;
        } elseif ($type === "float") {
            $result = (float)$value;
        } else {
            $result = $value;
        }

        return $result;
    }

    /**
     *
     * Parse options
     *
     * @param  Collection|null  $options
     * @return Collection|null
     *
     */
    public static function parseOptions(Collection|null $options): ?Collection
    {
        if ($options) {
            $parseOptions = Collection::init();
            $options->each(function ($key, $option) use ($parseOptions) {
                $parseOptions->pushKeyValue($option->value, $option->label);
            });
            return $parseOptions;
        }
        return null;
    }

    /**
     *
     * Validate the schema before save
     *
     * @param  Collection  $schema
     * @return void
     * @throws EntryException
     *
     */
    private static function validateSchema(Collection $schema): void
    {
        $schema->each(function ($key, $value) {
            if (!$value instanceof LayoutField) {
                throw new EntryException(self::INVALID_SCHEMA);
            }
        });
    }

    /**
     *
     * Process schema on fetch
     *
     * @param  stdClass|array|null  $value
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
        $schemaFromDb->each(function ($key, $field) use (&$schema) {
            foreach ($field->configs as &$input) {
                if (isset($input->settings)) {
                    if (array_key_exists('options', (array)$input->settings)) {
                        $input->settings['options'] = new Collection((array)$input->settings['options']);
                    }
                }
            }

            $valueParsed = ModelField::fromLayoutField($field);
            $schema->pushKeyValue($key, $valueParsed);
        });

        return $schema;
    }

    /**
     *
     * Process schema on store AKA convert all type to db object
     *
     * @param  Collection  $schema
     * @return Collection
     *
     */
    private function processSchemaOnStore(Collection $schema): Collection
    {
        $schemaForDb = Collection::init();
        $schema->each(function ($fieldId, $layoutField) use ($schemaForDb) {
            /**
             * @var LayoutField $layoutField
             */
            $layoutField = $layoutField->castFrom();

            $schemaForDb->pushKeyValue($fieldId, $layoutField);
        });
        return $schemaForDb;
    }

    /**
     *
     * Check if an entry layout have a related entry type
     *
     * @param  string|ObjectId  $entryLayoutId
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
     * @param  LocaleField  $titles
     * @param  Collection   $schema
     * @param  string|null  $slug
     * @return EntryLayout
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    private function createWithoutPermission(LocaleField $titles, Collection $schema, string $slug = null): EntryLayout
    {
        $dates = Dates::init();
        $author = User::$currentUser ?? User::anonymousUser();
        $authors = Authors::init($author);

        $slug = $slug ?? Text::from($titles->{Locale::default()})->slug()->value();
        $slug = self::generateSlug($slug);

        try {
            $entryLayoutId = $this->insert([
                'slug' => $slug,
                'titles' => $titles,
                'schema' => $schema,
                'authors' => $authors,
                'dates' => $dates,
                'is_trashed' => false
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'creating') . PHP_EOL . $exception->getMessage());
        }

        return $this->findById($entryLayoutId)->exec();
    }

    /**
     *
     * Update an entry layout
     *
     * @param  EntryLayout  $entryLayout
     * @param  Collection   $data
     * @return bool
     * @throws EntryException
     * @throws DatabaseException
     *
     */
    private function updateWithoutPermission(EntryLayout $entryLayout, Collection $data): bool
    {
        $author = User::$currentUser ?? User::anonymousUser();

        $update = [
            'dates' => Dates::updated($entryLayout->dates),
            'authors' => Authors::updated($entryLayout->authors, $author->_id)
        ];

        $titles = $data->get('titles');
        if ($titles) {
            $update['titles'] = $titles;
        }

        $schema = $data->get('schema');
        if ($schema) {
            $update['schema'] = $schema;
        }

        $slug = $data->get('slug');
        if ($slug) {
            $update['slug'] = $slug;
        }

        try {
            $qtyUpdated = $this->updateOne(['_id' => $entryLayout->_id], [
                '$set' => $update
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'updating') . PHP_EOL . $exception->getMessage());
        }

        return $qtyUpdated === 1;
    }

    /**
     *
     * Delete an entry layout to the trash
     *
     * @param  EntryLayout  $entryLayout
     * @return bool
     * @throws EntryException
     * @throws DatabaseException
     *
     */
    private function softDelete(EntryLayout $entryLayout): bool
    {
        $author = User::$currentUser ?? User::anonymousUser();
        $authors = Authors::deleted($entryLayout->authors, $author->_id);
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
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'soft deleting') . PHP_EOL . $exception->getMessage());
        }

        return $qtyUpdated === 1;
    }

    /**
     *
     * Delete an entry layout forever
     *
     * @param  string|ObjectId  $entryLayoutId
     * @return bool
     * @throws EntryException
     *
     */
    private function hardDelete(string|ObjectId $entryLayoutId): bool
    {
        try {
            $qtyDeleted = $this->deleteById((string)$entryLayoutId);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'hard deleting') . PHP_EOL . $exception->getMessage());
        }

        return $qtyDeleted === 1;
    }
}