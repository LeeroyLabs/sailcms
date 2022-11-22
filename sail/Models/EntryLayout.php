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
use SailCMS\Models\Entry\Field as FieldEntry;
use SailCMS\Types\Authors;
use SailCMS\Types\Dates;
use SailCMS\Types\Fields\LayoutField;
use SailCMS\Types\LocaleField;
use stdClass;

class EntryLayout extends Model
{
    /* Errors */
    const DATABASE_ERROR = 'Exception when %s an entry';
    const SCHEMA_MUST_CONTAIN_FIELDS = 'The schema must contains only SailCMS\Models\Entry\Field instances';

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
            if (!$field instanceof FieldEntry) {
                throw new FieldException(static::SCHEMA_MUST_CONTAIN_FIELDS);
            }

            $schema->pushKeyValue($field->generateKey(), $field->toLayoutField());
        });

        return $schema;
    }

    /**
     *
     * Get all entry layout TODO
     *
     * @return void
     * @throws DatabaseException
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public function getAll(): void
    {
        $this->hasPermissions(true);
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

        // TODO Validate schema
        // TODO Validate titles
        $schema = $this->processSchemaOnStore($schema);

        return $this->createWithoutPermission($titles, $schema);
    }

    /**
     *
     * Update the current schema with a field key, an array of keys and values and a field index
     * > The field key is generated at the entry layout creation with the Models\Entry\Field->generateKey method
     * > The to update array keys must be property of the Input type of the field index, if not there will be an error
     * > The field index is the index according to the baseConfigs of a Models\Entry\Field class,
     *      ¬ normally the Field class has only one element, but more complex can have many field
     *
     * @param string $fieldKey
     * @param array $toUpdate
     * @param string $fieldIndex
     * @return void
     *
     */
    public function updateSchemaConfig(string $fieldKey, array $toUpdate, string $fieldIndex = "0"): void
    {
        $this->schema->each(function ($currentFieldKey, &$field) use ($fieldKey, $toUpdate, $fieldIndex) {
            if ($currentFieldKey === $fieldKey) {
                $currentInput = $field->configs->get($fieldIndex)->toDbObject();
                $inputClass = $field->configs->get($fieldIndex)::class;

                foreach ($toUpdate as $key => $value) {
                    $currentInput->configs[$key] = $value;
                }

                $input = new $inputClass(new LocaleField($currentInput->labels), ...$currentInput->configs);
                $field->configs->pushKeyValue('0', $input);
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
            // TODO Validate titles
            $data->pushKeyValue('titles', $titles);
        }
        if ($schema) {
            // TODO Validate schema

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

        // TODO check if there is and entry type is using the layout (and it have related entries?)

        if ($soft) {
            $entryLayout = $this->findById($entryLayoutId)->exec();
            $result = $this->softDelete($entryLayout);
        } else {
            $result = $this->hardDelete($entryLayoutId);
        }

        return $result;
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

        $schemaFromDb->each(function ($key, $value) use ($schema) {
            $valueParsed = FieldEntry::fromLayoutField($value);
            $schema->pushKeyValue($key, $valueParsed->toLayoutField());
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