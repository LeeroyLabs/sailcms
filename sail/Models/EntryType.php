<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\Text;

class EntryType extends BaseModel
{
    const HANDLE_MISSING = "You must set the entry type handle in your data";
    const HANDLE_ALREADY_EXISTS = "Handle already exists";
    const TITLE_MISSING = "You must set the entry type title in your data";
    const DOES_NOT_EXISTS = "Entry type %s does not exists";
    const DATABASE_ERROR = "Exception when %s an entry type";

    private const _DEFAULT_HANDLE = "page";
    private const _DEFAULT_TITLE = "Page";
    private const _DEFAULT_URL_PREFIX = "";

    // Fields
    public string $collection_name;
    public string $title;
    public string $handle;
    public string $url_prefix;
    public ?string $entry_type_layout_id; // TODO implement layout!

    public function fields(bool $fetchAllFields = false): array
    {
        return [
            '_id',
            'title',
            'handle',
            'collection_name',
            'url_prefix',
            'entry_type_layout_id'
        ];
    }

    public function init(): void
    {
        $this->setPermissionGroup('entrytype');
    }

    /**
     *
     * Get a list of all available types
     *
     * @return Collection
     * @throws DatabaseException
     *
     */
    public static function getAll(): Collection
    {
        $instance = new static();
        return new Collection($instance->find([])->exec());
    }

    /**
     *
     * Use the settings to create the default type
     *
     * @return EntryType
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    public static function getDefaultType(): EntryType
    {
        // Get default values for default type
        $defaultHandle = $_ENV['SETTINGS']->get('entry.defaultType.handle') ?? self::_DEFAULT_HANDLE;
        $defaultTitle = $_ENV['SETTINGS']->get('entry.defaultType.title') ?? self::_DEFAULT_TITLE;
        $defaultUrlPrefix = $_ENV['SETTINGS']->get('entry.defaultType.url_prefix') ?? self::_DEFAULT_URL_PREFIX;

        $instance = new static();
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
     * @return EntryType
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    public static function getByCollectionName(string $collectionName): EntryType
    {
        $instance = new static();
        $entryType = $instance->findOne(['collection_name' => $collectionName])->exec();

        if (!$entryType) {
            throw new EntryException(sprintf(self::DOES_NOT_EXISTS, $collectionName));
        }

        return $entryType;
    }

    /**
     *
     * @param $handle
     * @return Entry
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    public static function getEntryModelByHandle(string $handle): Entry
    {
        $entryType = (new static())->getByHandle($handle);

        if (!$entryType) {
            throw new EntryException(sprintf(self::DOES_NOT_EXISTS, $handle));
        }

        return $entryType->getEntryModel();
    }

    /**
     *
     * Shortcut to get entry model and make queries
     *
     * @return Entry
     * @throws DatabaseException
     * @throws EntryException
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
     * @throws DatabaseException
     *
     */
    public function getById(string $id): ?EntryType
    {
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
     * @param  string|ObjectId|null  $entry_type_layout_id
     * @param  bool                  $getObject
     * @return array|EntryType|string|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function createOne(string $handle, string $title, string $url_prefix, string|ObjectId|null $entry_type_layout_id = null, bool $getObject = true): array|EntryType|string|null
    {
        $this->hasPermissions();

        return $this->create($handle, $title, $url_prefix, $entry_type_layout_id);
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
            throw new EntryException(sprintf(self::DOES_NOT_EXISTS, $handle));
        }

        return $this->update($entryType, $data);
    }

    /**
     *
     * Real deletion on the entry types
     *
     * @param  string  $entryTypeId
     * @return bool
     * @throws ACLException
     * @throws EntryException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function hardDelete(string $entryTypeId): bool
    {
        $this->hasPermissions();

        // TODO check if there is entry content before deleted it

        try {
            $qtyDeleted = $this->deleteById($entryTypeId);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'updating') . PHP_EOL . $exception->getMessage());
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
            throw new EntryException(self::HANDLE_ALREADY_EXISTS);
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
     * @param  string|ObjectId|null  $entry_type_layout_id
     * @param  bool                  $getObject
     * @return array|EntryType|string|null
     * @throws EntryException
     * @throws DatabaseException
     *
     */
    private function create(string $handle, string $title, string $url_prefix, string|ObjectId|null $entry_type_layout_id = null, bool $getObject = true): array|EntryType|string|null
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
                'entry_type_layout_id' => $entry_type_layout_id
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
     * @param  EntryType   $entryType
     * @param  Collection  $data
     * @return bool
     * @throws EntryException
     *
     */
    private function update(EntryType $entryType, Collection $data): bool
    {
        $title = $data->get('title');
        $url_prefix = $data->get('url_prefix');
        // TODO entry type layout id
        $update = [];

        if ($title) {
            $update['title'] = $title;
        }
        if ($url_prefix !== null) {
            $update['url_prefix'] = $url_prefix;
            // TODO update all entry url of this entry type
        }

        try {
            $this->updateOne(['_id' => $entryType->_id], [
                '$set' => $update
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'updating') . PHP_EOL . $exception->getMessage());
        }

        return true;
    }
}
