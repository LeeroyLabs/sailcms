<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\Cache;
use SailCMS\Collection;
use SailCMS\Contracts\Castable;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\Text;
use SailCMS\Types\Authors;
use SailCMS\Types\Dates;
use SailCMS\Types\EntryLayoutTab;

/**
 *
 *
 * @property string           $slug
 * @property string           $title
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
        'schema' => self::class,
        'authors' => Authors::class,
        'dates' => Dates::class
    ];

    /* Errors */
    public const DATABASE_ERROR = '6001: Exception when %s entry layout.';
    public const SCHEMA_IS_USED = '6002: Cannot delete the entry layout(s) because it is used by entry types.';
    public const DOES_NOT_EXISTS = '6003: Entry layout "%s" does not exists.';
    public const SCHEMA_INVALID_TAB_VALUE = '6004: Invalid tab value on tab #%s of your schema, must be an instance of EntryLayoutTab.';
    public const SCHEMA_INVALID_TAB_FIELD_ID = '6005: Invalid field id on tab #%s of your schema.';
    public const NOTHING_TO_UPDATE = '6006: No params filled, there is nothing to update.';

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
        if (!is_array($value)) {
            $value = (array)$value;
        }
        return new Collection($value);
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
            'title' => $this->title,
            'schema' => $this->schema,
            'authors' => $this->authors->castFrom(),
            'dates' => $this->dates->castFrom(),
            'is_trashed' => $this->is_trashed
        ];
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
        $entryLayout = $this->findOne(['slug' => $slug])->exec($cacheKey, $cacheTtl);

        $fieldIds = EntryLayout::getEntryFieldIds(new Collection([$entryLayout]));
        EntryLayout::fetchFields($fieldIds, new Collection([$entryLayout]));
        return $entryLayout;
    }

    /**
     *
     * Find one user with filters
     * # TODO this method is a mess
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
                $entryLayout = $this->findById($filters['_id'])->exec();
                $fieldIds = EntryLayout::getEntryFieldIds(new Collection([$entryLayout]));
                EntryLayout::fetchFields($fieldIds, new Collection([$entryLayout]));
                return $entryLayout;
            }

            // Cache Time To Live value from setting or default
            $cacheTtl = setting('entry.cacheTtl', Cache::TTL_WEEK);
            $cacheKey = self::ENTRY_LAYOUT_ID_ . $filters['_id'];
            $entryLayout = $this->findById($filters['_id'])->exec($cacheKey, $cacheTtl);
        } else {
            $entryLayout = $this->findOne($filters)->exec();
        }

        if (!$entryLayout) {
            return null;
        }

        $fieldIds = EntryLayout::getEntryFieldIds(new Collection([$entryLayout]));
        EntryLayout::fetchFields($fieldIds, new Collection([$entryLayout]));

        return $entryLayout;
    }

    /**
     *
     * Get usage count of an entry field for a given id
     *
     * @param  string  $entryFieldId
     * @return int
     *
     */
    public static function countUsedEntryField(string $entryFieldId)
    {
        return (new static())->count(['schema.fields' => ['$in' => [$entryFieldId]]]);
    }

    /**
     *
     * Create an entry layout
     *
     * @param  string       $title
     * @param  Collection   $schema
     * @param  string|null  $slug  slug is set to $title->{Locale::default()}
     * @return EntryLayout
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function create(string $title, Collection $schema, ?string $slug = null): EntryLayout
    {
        $this->hasPermissions();

        // Schema preparation
        self::validateSchema($schema);

        return $this->createWithoutPermission($title, $schema, $slug);
    }

    /**
     *
     * Update an entry layout for a given id or entryLayout instance
     *
     * @param  EntryLayout|string  $entryLayoutOrId
     * @param  string|null         $title
     * @param  Collection|null     $schema
     * @param  string|null         $slug
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function updateById(EntryLayout|string $entryLayoutOrId, ?string $title, ?Collection $schema, ?string $slug): bool
    {
        $this->hasPermissions();

        if (is_string($entryLayoutOrId)) {
            $entryLayout = $this->findById($entryLayoutOrId)->exec();
        } else {
            $entryLayout = $entryLayoutOrId;
        }

        $data = Collection::init();

        if ($title) {
            $data->pushKeyValue('title', $title);
        }

        if ($schema) {
            self::validateSchema($schema);
            $data->pushKeyValue('schema', $schema);
        }

        if ($slug) {
            $data->pushKeyValue('slug', $slug);
        }

        if ($data->length > 0) {
            return $this->updateWithoutPermission($entryLayout, $data);
        }
        return false;
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
        if (self::hasEntryTypes([$entryLayoutId])) {
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
     * Delete many by ids in soft mode or not
     *
     * @param  array|Collection  $ids
     * @param  bool              $soft
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function deleteManyByIds(array|Collection $ids, bool $soft = true): bool
    {
        $this->hasPermissions();

        // Is one of them is used
        if (self::hasEntryTypes($ids)) {
            throw new EntryException(self::SCHEMA_IS_USED);
        }

        // All ids must be ObjectId
        $ids = self::ensureObjectIds($ids, true);

        if ($soft) {
            return $this->softDeleteMany($ids);
        }
        return $this->hardDeleteMany($ids);
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
        $schema->each(function ($i, $tabFields) {
            if (!$tabFields instanceof EntryLayoutTab) {
                throw new EntryException(sprintf(self::SCHEMA_INVALID_TAB_VALUE, $i + 1));
            }

            foreach ($tabFields->fields as $fieldId) {
                try {
                    (new EntryLayout())->ensureObjectId($fieldId);
                } catch (\Throwable $exception) {
                    throw new EntryException(sprintf(self::SCHEMA_INVALID_TAB_FIELD_ID, $i + 1));
                }
            }
        });
    }

    /**
     *
     * Check if an entry layout have a related entry type
     *
     * @param  Collection|array  $entryLayoutIds
     * @return bool
     */
    public static function hasEntryTypes(Collection|array $entryLayoutIds): bool
    {
        if ($entryLayoutIds instanceof Collection) {
            $entryLayoutIds = $entryLayoutIds->unwrap();
        }

        // All ids must be string
        $entryLayoutIds = array_map(function ($value) {
            return (string)$value;
        }, $entryLayoutIds);

        $entryTypeCount = (new EntryType())->count(['entry_layout_id' => ['$in' => $entryLayoutIds]]);
        return $entryTypeCount > 0;
    }

    /**
     *
     * Get field in schema
     *
     * @param  Collection  $schema
     * @param  string      $fieldKey
     * @return EntryField|null
     *
     */
    public static function getFieldInSchema(Collection $schema, string $fieldKey): ?EntryField
    {
        $entryField = null;

        foreach ($schema as $tab) {
            /**
             * @var EntryLayoutTab $tab
             */
            $fields = $tab->fields;
            foreach ($fields as $field) {
                /**
                 * @var EntryField $field
                 */
                if ($field->key == $fieldKey) {
                    $entryField = $field;
                    break;
                }
            }

            if ($entryField) {
                break;
            }
        }

        return $entryField;
    }

    /**
     *
     * Get entry field ids from schema of a collection of entry layouts
     *
     * @param  Collection  $entryLayouts
     * @return array
     *
     */
    public static function getEntryFieldIds(Collection $entryLayouts): array
    {
        $fieldIds = [];

        $entryLayouts->each(function ($k, $entryLayout) use (&$fieldIds) {
            foreach ($entryLayout->schema as $fieldTab) {
                if (isset($fieldTab->fields)) {
                    foreach ($fieldTab->fields as $fieldId) {
                        if (!in_array($fieldId, $fieldIds)) {
                            $fieldIds[] = $fieldId;
                        }
                    }
                }
            }
        });

        return (new self)->ensureObjectIds($fieldIds, true);
    }

    /**
     *
     * Fetch fields for a collection of schema of entry layouts
     *
     * @param  array       $fieldIds
     * @param  Collection  $entryLayouts
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function fetchFields(array $fieldIds, Collection $entryLayouts): void
    {
        $fields = (new EntryField)->getList(['_id' => ['$in' => $fieldIds]]);
        $fieldsById = [];
        $fields->each(function ($k, $field) use (&$fieldsById) {
            /**
             * @var EntryField $field
             */
            $fieldsById[(string)$field->_id] = $field;
        });

        $entryLayouts->each(function ($k, $entryLayout) use ($fieldsById) {
            foreach ($entryLayout->schema as $tab) {
                if ($tab->fields) {
                    foreach ($tab->fields as &$fieldId) {
                        $fieldId = $fieldsById[$fieldId] ?? null;
                    }
                }
            }
        });
    }

    /**
     *
     * Create an entry layout
     *
     * @param  string       $title
     * @param  Collection   $schema
     * @param  string|null  $slug
     * @return EntryLayout
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    private function createWithoutPermission(string $title, Collection $schema, string $slug = null): EntryLayout
    {
        $dates = Dates::init();
        $author = User::$currentUser ?? User::anonymousUser();
        $authors = Authors::init($author);

        $slug = $slug ?? Text::from($title)->slug()->value();
        $slug = self::generateSlug($slug);

        try {
            $entryLayoutId = $this->insert([
                'slug' => $slug,
                '$title' => $title,
                'schema' => $schema,
                'authors' => $authors,
                'dates' => $dates,
                'is_trashed' => false
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'creating an') . PHP_EOL . $exception->getMessage());
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

        $title = $data->get('title');
        if ($title) {
            $update['title'] = $title;
        }

        $schema = $data->get('schema');
        if ($schema) {
            $update['schema'] = $schema;
        }

        $slug = $data->get('slug');
        if ($slug) {
            $update['slug'] = self::generateSlug($slug, (string)$entryLayout->_id);
        }

        try {
            $qtyUpdated = $this->updateOne(['_id' => $entryLayout->_id], [
                '$set' => $update
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'updating an') . PHP_EOL . $exception->getMessage());
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
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'soft deleting an') . PHP_EOL . $exception->getMessage());
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
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'hard deleting an') . PHP_EOL . $exception->getMessage());
        }

        return $qtyDeleted === 1;
    }

    /**
     *
     * Soft delete many entry layouts by ids
     *
     * @param  array  $ids
     * @return bool
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    private function softDeleteMany(array $ids): bool
    {
        $author = User::$currentUser ?? User::anonymousUser();
        $now = time();

        try {
            $count = $this->updateMany(['_id' => ['$in' => $ids]], ['$set' => [
                'authors.deleted_by' => $author->_id,
                'dates.deleted' => $now,
                'is_trashed' => true
            ]]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'soft deleting a batch of') . PHP_EOL . $exception->getMessage());
        }

        return $count == count($ids);
    }

    /**
     *
     * Delete many entry fields by id
     *
     * @param  array  $ids
     * @return bool
     * @throws EntryException
     *
     */
    private function hardDeleteMany(array $ids): bool
    {
        $ids = $this->ensureObjectIds($ids, true);

        try {
            $count = $this->deleteMany(['_id' => ['$in' => $ids]]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'hard deleting a batch of') . PHP_EOL . $exception->getMessage());
        }

        return $count === count($ids);
    }
}