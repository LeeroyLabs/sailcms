<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\FieldException;
use SailCMS\Models\Entry\Field as FieldEntry;
use SailCMS\Types\Authors;
use SailCMS\Types\Dates;
use SailCMS\Types\LocaleField;
use stdClass;

class EntryLayout extends Model
{
    /* Errors */
    const DATABASE_ERROR = 'Exception when %s an entry';
    const SCHEMA_MUST_CONTAIN_FIELDS = 'The schema must contains only SailCMS\Models\Entry\Field instances';

    const ACL_HANDLE = "entrylayout";

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

    public function fields(bool $fetchAllFields = false): array
    {
        return ['_id', 'titles', 'schema', 'authors', 'dates'];
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
        $fields->each(function ($key, $field) use ($schema)
        {
            if (!$field instanceof FieldEntry) {
                throw new FieldException(static::SCHEMA_MUST_CONTAIN_FIELDS);
            }

            $schema->pushKeyValue($field->generateKey(), $field->toLayoutField());
        });

        return $schema;
    }

    public function getAll()
    {
        $this->hasPermissions(true);
    }

    public function one(array $filters)
    {
        $this->hasPermissions(true);

        return $this->findOne($filters)->exec();
    }

    public function create(LocaleField $titles, Collection $schema): EntryLayout
    {
        $this->hasPermissions();

        // TODO Validate schema
        // TODO Validate titles

        return $this->createWithoutPermission($titles, $schema);
    }

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
            $data->pushKeyValue('schema', $schema);
        }

        if ($data->length > 0) {
            return $this->updateWithoutPermission($entry, $data);
        }
        return false;
    }

    public function delete(string|ObjectId $entryLayoutId, bool $soft = true): bool
    {
        $this->hasPermissions();

        // TODO check if there is and entry type is using the layout (and it have related entries?)

        if ($soft) {
            $entryLayout = $this->findById($entryLayoutId);
            $result = $this->softDelete($entryLayout);
        } else {
            $result = $this->hardDelete($entryLayoutId);
        }

        return $result;
    }

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

    protected function processOnStore(string $field, mixed $value): mixed
    {
        return match ($field) {
            'schema' => $this->processSchemaOnStore($value),
            default => $value,
        };
    }

    /**
     *
     * Process schema on fetch
     *
     * @param  stdClass|array|null  $value
     * @return Collection
     */
    private function processSchemaOnFetch(stdClass|array|null $value): Collection
    {
        if (!$value) {
            return Collection::init();
        }

//        $schema = new Collection((array)$value);
//
//        $schema->each(function ($key, &$value)
//        {
//            $value = FieldEntry::fromLayoutField($value);
//        });

        return new Collection((array)$value);
    }

    private function processSchemaOnStore(Collection &$data): Collection
    {
//        print_r($data);
//        $data->each(function ($fieldId, &$fieldConfigs)
//        {
//            /**
//             * @var Collection $fieldConfigs
//             */
//            $fieldConfigs->each(function ($key, $inputField) use ($fieldConfigs)
//            {
//                /**
//                 * @var FieldInput $inputField
//                 */
//                $inputFieldForDb = $inputField->toDBObject();
//                $fieldConfigs->pushKeyValue($key, $inputFieldForDb);
//            });
//        });

        print_r($data);
        return $data;
    }

    /**
     *
     * Create an entry layout
     *
     * @param  LocaleField  $titles
     * @param  Collection   $schema
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
                'dates' => $dates
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
     * @param  EntryLayout  $entryLayout
     * @param  Collection   $data
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
     * @param  EntryLayout  $entryLayout
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
            throw new EntryException(sprintf(static::DATABASE_ERROR, 'hard deleting') . PHP_EOL . $exception->getMessage());
        }

        return $qtyDeleted === 1;
    }
}