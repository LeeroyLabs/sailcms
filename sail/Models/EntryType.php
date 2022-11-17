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

class EntryType extends Model
{
    /* errors */
    const HANDLE_MISSING = "You must set the entry type handle in your data";
    const HANDLE_ALREADY_EXISTS = "Handle already exists";
    const TITLE_MISSING = "You must set the entry type title in your data";
    const CANNOT_DELETE = "You must emptied all related entries before deleting an entry type";
    const DOES_NOT_EXISTS = "Entry type %s does not exists";
    const DATABASE_ERROR = "Exception when %s an entry type";

    const ACL_HANDLE = "entrytype";

    /* default entry type */
    private const _DEFAULT_HANDLE = "page";
    private const _DEFAULT_TITLE = "Page";
    private const _DEFAULT_URL_PREFIX = "";

    /* fields */
    public string $collection_name;
    public string $title;
    public string $handle;
    public string $url_prefix;
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

    public function init(): void
    {
        $this->setPermissionGroup(static::ACL_HANDLE);
    }

    /**
     *
     * Get a list of all available types
     *
     * @param  bool  $api
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
     * Use the settings to create the default type
     *
     * @param  bool  $api
     * @return EntryType
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public static function getDefaultType(bool $api = false): EntryType
    {
        $instance = new static();
        if ($api) {
            $instance->hasPermissions(true);
        }

        // Get default values for default type
        $defaultHandle = $_ENV['SETTINGS']->get('entry.defaultType.handle') ?? static::_DEFAULT_HANDLE;
        $defaultTitle = $_ENV['SETTINGS']->get('entry.defaultType.title') ?? static::_DEFAULT_TITLE;
        $defaultUrlPrefix = $_ENV['SETTINGS']->get('entry.defaultType.url_prefix') ?? static::_DEFAULT_URL_PREFIX;

        $entryType = $instance->getByHandle($defaultHandle);

        if (!$entryType) {
            $entryType = $instance->create($defaultHandle, $defaultTitle, $defaultUrlPrefix);
        }
        return $entryType;
    }

    /**
     *
     * Get an entry type by his collection name
     *
     * @param  string  $collectionName
     * @param  bool    $api
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
            throw new EntryException(sprintf(static::DOES_NOT_EXISTS, $collectionName));
        }

        return $entryType;
    }

    /**
     *
     * Get an entry model instance by entry type handle
     *
     * @param  string  $handle
     * @param  bool    $api
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

        $entryType = $instance->getByHandle($handle);
        if (!$entryType) {
            throw new EntryException(sprintf(static::DOES_NOT_EXISTS, $handle));
        }

        return $entryType->getEntryModel();
    }

    /**
     *
     * Shortcut to get entry model and make queries
     *
     * @return Entry
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function getEntryModel(): Entry
    {
        return new Entry($this->collection_name);
    }

    /**
     *
     * Get an entryType by id
     *
     * @param  string  $id
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
     * @param  string  $handle
     * @return EntryType|null
     * @throws DatabaseException
     *
     */
    public function getByHandle(string $handle): ?EntryType
    {
        return $this->findOne(['handle' => $handle])->exec();
    }

    /**
     *
     * Wrapper to handle permission for entry creation
     *
     * @param  string                $handle
     * @param  string                $title
     * @param  string                $url_prefix
     * @param  string|ObjectId|null  $entry_layout_id
     * @param  bool                  $getObject
     * @return array|EntryType|string|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function createOne(string $handle, string $title, string $url_prefix, string|ObjectId|null $entry_layout_id = null, bool $getObject = true): array|EntryType|string|null
    {
        $this->hasPermissions();

        return $this->create($handle, $title, $url_prefix, $entry_layout_id);
    }

    /**
     *
     * Wrapper to handle permission for entry modification by handle
     *
     * @param  string      $handle
     * @param  Collection  $data
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

        $entryType = $this->findOne(['handle' => $handle])->exec();

        if (!$entryType) {
            throw new EntryException(sprintf(static::DOES_NOT_EXISTS, $handle));
        }

        return $this->update($entryType, $data);
    }

    /**
     *
     * Real deletion on the entry types
     *
     * @param  string|ObjectId  $entryTypeId
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
            throw new EntryException(sprintf(static::DOES_NOT_EXISTS, $entryTypeId));
        }

        // Check if there is entries content
        $counts = ($entryType->getEntryModel())->countEntries();
        if ($counts > 0) {
            throw new EntryException(static::CANNOT_DELETE);
        }

        try {
            $qtyDeleted = $this->deleteById($entryTypeId);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(static::DATABASE_ERROR, 'updating') . PHP_EOL . $exception->getMessage());
        }

        return $qtyDeleted === 1;
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
            throw new EntryException(static::HANDLE_MISSING);
        }
        if ($field === "title" && empty($value)) {
            throw new EntryException(static::TITLE_MISSING);
        }

        return parent::processOnStore($field, $value);
    }

    /**
     *
     * Check if handle is available
     *
     * @param  string  $handle
     * @return void
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    private function checkHandle(string $handle): void
    {
        // Check everytime if the handle is already exists
        if ($this->getByHandle($handle) !== null) {
            throw new EntryException(static::HANDLE_ALREADY_EXISTS);
        }
        // TODO reserved word
    }

    /**
     *
     * Get collection name with handle
     *
     * @param  string  $handle
     * @return string
     *
     */
    private function getCollectionName(string $handle): string
    {
        return Text::snakeCase(Text::deburr(Text::inflector()->pluralize($handle)[0]));
    }

    /**
     *
     * Create an entry type
     *
     * @param  string                $handle
     * @param  string                $title
     * @param  string                $url_prefix
     * @param  string|ObjectId|null  $entry_layout_id
     * @param  bool                  $getObject  throw new PermissionException('Permission Denied', 0403);
     * @return array|EntryType|string|null
     * @throws EntryException
     * @throws DatabaseException
     *
     */
    private function create(string $handle, string $title, string $url_prefix, string|ObjectId|null $entry_layout_id = null, bool $getObject = true): array|EntryType|string|null
    {
        $this->checkHandle($handle);

        $collection_name = $this->getCollectionName($handle);

        // Create the entry type
        try {
            $entryTypeId = $this->insert([
                'collection_name' => $collection_name,
                'handle' => $handle,
                'title' => $title,
                'url_prefix' => $url_prefix,
                'entry_layout_id' => $entry_layout_id
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(static::DATABASE_ERROR, 'creating') . PHP_EOL . $exception->getMessage());
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
     * @param  EntryType   $entryType
     * @param  Collection  $data
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    private function update(EntryType $entryType, Collection $data): bool
    {
        $title = $data->get('title');
        $url_prefix = $data->get('url_prefix');
        $entry_layout_id = $data->get('entry_layout_id');

        $update = [];

        // Check if field has been sent
        if ($title) {
            $update['title'] = $title;
        }
        if ($url_prefix !== null) {
            $update['url_prefix'] = $url_prefix;
        }
        if ($entry_layout_id) {
            $update['entry_layout_id'] = $entry_layout_id;
        }

        try {
            $this->updateOne(['_id' => $entryType->_id], [
                '$set' => $update
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(static::DATABASE_ERROR, 'updating') . PHP_EOL . $exception->getMessage());
        }

        // Update url of related entries
        if (array_key_exists('url_prefix', $update)) {
            ($entryType->getEntryModel())->updateEntriesUrl($update['url_prefix']);
        }

        return true;
    }
}
