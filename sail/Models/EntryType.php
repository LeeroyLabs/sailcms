<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\Text;
use SailCMS\Types\LocaleField;

class EntryType extends Model
{
    /* Errors */
    const HANDLE_MISSING = '4001: You must set the entry type handle in your data.';
    const HANDLE_ALREADY_EXISTS = '4002: Handle already exists.';
    const HANDLE_USE_RESERVED_WORD = '4003: The "%s" word is reserved to create an entry type.';
    const TITLE_MISSING = '4004: You must set the entry type title in your data.';
    const CANNOT_DELETE = '4005: You must emptied all related entries before deleting an entry type.';
    const DOES_NOT_EXISTS = '4006: Entry type "%s" does not exists.';
    const DATABASE_ERROR = '4007: Exception when "%s" an entry type.';
    const CANNOT_UPDATE_DEFAULT_TYPE = '4008: Cannot update default type page, use general settings for that';

    const ACL_HANDLE = "entrytype";

    const RESERVED_WORDS_FOR_HANDLE = [
        'entry',
        'entries',
        'entry_type',
        'entry_types',
        'entry_layout',
        'entry_layouts',
        'user',
        'users',
        'category',
        'categories',
        'asset',
        'assets',
        'config',
        'configs',
        'log',
        'logs',
        'tfa_data',
        'role',
        'roles',
        'email',
        'emails',
        'csrf'
    ];

    /* default entry type */
    private const DEFAULT_HANDLE = "page";
    private const DEFAULT_TITLE = "Page";
    private const DEFAULT_URL_PREFIX = ['en' => '', 'fr' => ''];

    /* fields */
    public string $collection_name;
    public string $title;
    public string $handle;
    public LocaleField $url_prefix;
    public ?string $entry_layout_id;

    public function fields(bool $fetchAllFields = false): array
    {
        return [
            '_id',
            'title',
            'handle',
            'collection_name',
            'url_prefix',
            'entry_layout_id'
        ];
    }

    /**
     *
     * Initialization for entry type
     *
     * @return void
     *
     */
    public function init(): void
    {
        $this->setPermissionGroup(self::ACL_HANDLE);
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
            'title' => $this->title,
            'handle' => $this->handle,
            'url_prefix' => $this->url_prefix->toDBObject(),
            'entry_layout_id' => $this->entry_layout_id ?? ""
        ];
    }

    /**
     *
     * Get a list of all available types
     *
     * @param bool $api
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function getAll(bool $api = false): Collection
    {
        if ($api) {
            (new static())->hasPermissions(true);
        }

        $instance = new static();
        return new Collection($instance->find([])->exec());
    }

    /**
     *
     * Find all entry type according to the given filters
     *
     * @param array|Collection $filters
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function findAll(array|Collection $filters): Collection
    {
        (new static())->hasPermissions(true);

        if ($filters instanceof Collection) {
            $filters = $filters->unwrap();
        }

        $instance = new static();
        return new Collection($instance->find($filters)->exec());
    }

    /**
     *
     * Use the settings to create the default type
     *
     * @param bool $api
     * @param bool $avoidUpdate
     * @return EntryType
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     */
    public static function getDefaultType(bool $api = false, bool $avoidUpdate = false): EntryType
    {
        $instance = new static();
        if ($api) {
            $instance->hasPermissions(true);
        }

        // Get default values for default type
        $defaultHandle = self::DEFAULT_HANDLE;
        $defaultTitle = $_ENV['SETTINGS']->get('entry.defaultType.title', self::DEFAULT_TITLE);
        $defaultUrlPrefix = new LocaleField($_ENV['SETTINGS']->get('entry.defaultType.urlPrefix', self::DEFAULT_URL_PREFIX));
        $defaultEntryLayoutId = $_ENV['SETTINGS']->get('entry.defaultType.entryLayoutId', false);

        $entryType = $instance->findOne(['handle' => $defaultHandle])->exec();

        if (!$entryType) {
            $entryType = $instance->createWithoutPermission($defaultHandle, $defaultTitle, $defaultUrlPrefix, $defaultEntryLayoutId);
        } else if (!$avoidUpdate
            && ($entryType->title != $defaultTitle || $entryType->url_prefix->toDBObject() != $defaultUrlPrefix->toDBObject() || $entryType->entry_layout_id != $defaultEntryLayoutId)) {

            // Update the settings because it changed.
            $result = $instance->updateWithoutPermission($entryType, $defaultTitle, $defaultUrlPrefix, $defaultEntryLayoutId);

            if ($result) {
                $entryType = $instance->findOne(['handle' => $defaultHandle])->exec();
            }
        }
        return $entryType;
    }

    /**
     *
     * Get an entry type by his collection name
     *
     * @param string $collectionName
     * @param bool $api
     * @return EntryType
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public static function getByCollectionName(string $collectionName, bool $api = false): EntryType
    {
        $instance = new static();
        if ($api) {
            $instance->hasPermissions(true);
        }

        $entryType = $instance->findOne(['collection_name' => $collectionName])->exec();
        if (!$entryType) {
            throw new EntryException(sprintf(self::DOES_NOT_EXISTS, $collectionName));
        }

        return $entryType;
    }

    /**
     *
     * Get an entry model instance by entry type handle
     *
     * @param string $handle
     * @param bool $api
     * @return Entry
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public static function getEntryModelByHandle(string $handle, bool $api = false): Entry
    {
        $instance = new static();
        if ($api) {
            $instance->hasPermissions(true);
        }

        $entryType = $instance->getByHandle($handle, $api);
        if (!$entryType) {
            throw new EntryException(sprintf(self::DOES_NOT_EXISTS, $handle));
        }

        return $entryType->getEntryModel($entryType);
    }

    /**
     *
     * Shortcut to get entry model and make queries
     *
     * @param EntryType|null $entryType
     * @return Entry
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function getEntryModel(EntryType $entryType = null): Entry
    {
        // Pass the entry type to avoid a query
        if (isset($this->_id) && !$entryType) {
            return new Entry($this->collection_name, $this);
        }

        return new Entry($this->collection_name, $entryType);
    }

    /**
     *
     * Get an entryType by id
     *
     * @param string $id
     * @return EntryType|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function getById(string $id): ?EntryType
    {
        $this->hasPermissions(true);

        return $this->findById($id)->exec();
    }

    /**
     *
     * Get an entryType by handle
     *
     * @param string $handle
     * @return EntryType|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     * @throws EntryException
     *
     */
    public function getByHandle(string $handle, bool $api = false): ?EntryType
    {
        if ($api) {
            $this->hasPermissions(true);
        }

        if ($handle == self::DEFAULT_HANDLE) {
            return self::getDefaultType();
        }

        return $this->findOne(['handle' => $handle])->exec();
    }

    /**
     *
     * Wrapper to handle permission for entry creation
     *
     * @param string $handle
     * @param string $title
     * @param LocaleField $urlPrefix
     * @param string|ObjectId|null $entryLayoutId
     * @param bool $getObject
     * @return array|EntryType|string|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function create(string $handle, string $title, LocaleField $urlPrefix, string|ObjectId|null $entryLayoutId = null, bool $getObject = true): array|EntryType|string|null
    {
        $this->hasPermissions();

        return $this->createWithoutPermission($handle, $title, $urlPrefix, $entryLayoutId);
    }

    /**
     *
     * Wrapper to handle permission for entry modification by handle
     *
     * @param string $handle
     * @param Collection $data
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function updateByHandle(string $handle, Collection $data): bool
    {
        $this->hasPermissions();

        if ($handle == self::DEFAULT_HANDLE) {
            throw new EntryException(self::CANNOT_UPDATE_DEFAULT_TYPE);
        }

        $entryType = $this->findOne(['handle' => $handle])->exec();

        if (!$entryType) {
            throw new EntryException(sprintf(self::DOES_NOT_EXISTS, $handle));
        }

        $title = $data->get('title');
        $urlPrefix = $data->get('url_prefix');
        $entryLayoutId = $data->get('entry_layout_id');

        return $this->updateWithoutPermission($entryType, $title, $urlPrefix, $entryLayoutId);
    }

    /**
     *
     * Real deletion on the entry types
     *
     * @param string|ObjectId $entryTypeId
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     */
    public function hardDelete(string|ObjectId $entryTypeId): bool
    {
        $this->hasPermissions();

        // Cannot delete if it not exists
        $entryType = $this->findById($entryTypeId)->exec();
        if (!$entryType) {
            throw new EntryException(sprintf(self::DOES_NOT_EXISTS, $entryTypeId));
        }

        // Check if there is entries content
        $counts = ($entryType->getEntryModel($entryType))->countEntries();
        if ($counts > 0) {
            throw new EntryException(self::CANNOT_DELETE);
        }

        try {
            $qtyDeleted = $this->deleteById($entryTypeId);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'updating') . PHP_EOL . $exception->getMessage());
        }

        return $qtyDeleted === 1;
    }

    protected function processOnFetch(string $field, mixed $value): mixed
    {
        if ($field == "url_prefix") {
            if (is_string($value)) {
                $value = ['fr' => $value, 'en' => $value];
            }
            return new LocaleField($value);
        }
        return parent::processOnFetch($field, $value);
    }

    /**
     *
     * Validation on store
     *
     * @throws EntryException
     *
     */
    protected function processOnStore(string $field, mixed $value): mixed
    {
        // Data verification
        if ($field === "handle" && empty($value)) {
            throw new EntryException(self::HANDLE_MISSING);
        }
        if ($field === "title" && empty($value)) {
            throw new EntryException(self::TITLE_MISSING);
        }

        return parent::processOnStore($field, $value);
    }

    /**
     *
     * Check if handle is available
     *
     * @param string $handle
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    private function checkHandle(string $handle): void
    {
        // Check in reserved word for handle
        if (in_array($handle, self::RESERVED_WORDS_FOR_HANDLE)) {
            throw new EntryException(sprintf(self::HANDLE_USE_RESERVED_WORD, $handle));
        }

        // Check everytime if the handle is already exists
        if ($handle == self::DEFAULT_HANDLE || $this->getByHandle($handle) !== null) {
            throw new EntryException(self::HANDLE_ALREADY_EXISTS);
        }
    }

    /**
     *
     * Get collection name with handle
     *
     * @param string $handle
     * @return string
     *
     */
    private function getCollectionName(string $handle): string
    {
        return Text::snakeCase(Text::deburr(Text::pluralize($handle)));
    }

    /**
     *
     * Create an entry type
     *
     * @param string $handle
     * @param string $title
     * @param LocaleField $urlPrefix
     * @param string|ObjectId|null $entryLayoutId
     * @param bool $getObject throw new PermissionException('Permission Denied', 0403);
     * @return array|EntryType|string|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    private function createWithoutPermission(string $handle, string $title, LocaleField $urlPrefix, string|ObjectId|null $entryLayoutId = null, bool $getObject = true): array|EntryType|string|null
    {
        $this->checkHandle($handle);

        $collectionName = $this->getCollectionName($handle);

        // Create the entry type
        try {
            $entryTypeId = $this->insert([
                'collection_name' => $collectionName,
                'handle' => $handle,
                'title' => $title,
                'url_prefix' => $urlPrefix->toDBObject(),
                'entry_layout_id' => $entryLayoutId ? (string)$entryLayoutId : null
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'creating') . PHP_EOL . $exception->getMessage());
        }

        if ($getObject) {
            return $this->findById($entryTypeId)->exec();
        }

        return $entryTypeId;
    }

    /**
     *
     * Update the entry type
     *
     * @param EntryType $entryType
     * @param string|null $title
     * @param LocaleField|null $urlPrefix
     * @param string|bool|null $entryLayoutId
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     */
    private function updateWithoutPermission(EntryType $entryType, ?string $title, ?LocaleField $urlPrefix, string|bool|null $entryLayoutId): bool
    {
        $update = [];

        // Check if field has been sent
        if ($title) {
            $update['title'] = $title;
        }
        if ($urlPrefix) {
            $update['url_prefix'] = $urlPrefix;
        }
        if ($entryLayoutId === false)
            $update['entry_layout_id'] = null;
        else if ($entryLayoutId) {
            $update['entry_layout_id'] = $entryLayoutId;
        }

        if (count($update) > 0) {
            try {
                $this->updateOne(['_id' => $entryType->_id], [
                    '$set' => $update
                ]);
            } catch (DatabaseException $exception) {
                throw new EntryException(sprintf(self::DATABASE_ERROR, 'updating') . PHP_EOL . $exception->getMessage());
            }

            // Update url of related entries
            if (array_key_exists('url_prefix', $update)) {
                (new Entry($entryType->collection_name))->updateEntriesUrl($update['url_prefix']);
            }

            return true;
        }
        // Nothing to update
        return false;
    }
}
