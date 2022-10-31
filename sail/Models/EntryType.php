<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\ACL;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Text;

class EntryType extends BaseModel
{
    const HANDLE_MISSING_IN_COLLECTION = "You must set the entry type handle in your data collection";
    const HANDLE_ALREADY_EXISTS = "Handle already exists";
    const TITLE_MISSING_IN_COLLECTION = "You must set the entry type title in your data collection";
    const CANNOT_CREATE_ENTRY_TYPE = "You don't have the right to create an entry type";
    const DOES_NOT_EXISTS = "Entry type %s does not exists";
    const DATABASE_ERROR = "Exception when creating an entry type";

    // Fields
    public string $collection_name;
    public string $title;
    public string $handle;
    public string $url_prefix;
    public string $entry_type_layout_id;

    public function fields(bool $fetchAllFields = false): array
    {
        return [
            'title',
            'handle',
            'collection_name',
            'url_prefix',
            'entry_type_layout_id'
        ];
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
    public function getByHandle(string $handle): EntryType|null
    {
        return $this->findOne(['handle' => $handle])->exec();
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
     */
    public function create(string $handle, string $title, string $url_prefix, string|ObjectId $entry_type_layout_id = null, bool $getObject = true): array|EntryType|string|null
    {
        $this->_hasPermission();

        return $this->_create($handle, $title, $url_prefix, $entry_type_layout_id);
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
     */
    public function updateByHandle(string $handle, Collection $data): bool
    {
        $this->_hasPermission();

        $entryType = $this->findOne(['handle' => $handle])->exec();

        if (!$entryType) {
            throw new EntryException(sprintf(self::DOES_NOT_EXISTS, $handle));
        }

        return $this->_update($entryType, $data);
    }

    /**
     *
     * @param  string  $entryTypeId
     * @return bool
     * @throws ACLException|DatabaseException|EntryException
     */
    public function softDelete(string $entryTypeId): bool
    {
        // TODO do we will have delete permission ?
        $this->_hasPermission();

        return $this->_delete($entryTypeId);
    }

    /**
     *
     * @param  string  $entryTypeId
     * @return bool
     * @throws ACLException|DatabaseException|EntryException
     */
    public function hardDelete(string $entryTypeId): bool
    {
        // TODO do we will have delete permission ?
        $this->_hasPermission();

        return $this->_delete($entryTypeId, true);
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
            throw new EntryException(self::HANDLE_MISSING_IN_COLLECTION);
        }
        if ($field === "title" && empty($value)) {
            throw new EntryException(self::TITLE_MISSING_IN_COLLECTION);
        }

        return parent::processOnStore($field, $value);
    }

    /**
     * @throws DatabaseException
     * @throws ACLException
     * @throws EntryException
     */
    private function _hasPermission()
    {
        if (!ACL::hasPermission(User::$currentUser, ACL::write('EntryType'))) {
            throw new EntryException(self::CANNOT_CREATE_ENTRY_TYPE);
        }
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
     * @throws DatabaseException
     * @throws EntryException
     */
    private function _create(string $handle, string $title, string $url_prefix, string|ObjectId $entry_type_layout_id = null, bool $getObject = true): array|EntryType|string|null
    {
        // Check everytime if the handle is already exists
        if ($this->getByHandle($handle) !== null) {
            throw new EntryException(self::HANDLE_ALREADY_EXISTS);
        }

        $collection_name = Text::snakeCase(Text::deburr(Text::inflector()->pluralize($handle)[0]));

        // Create the entry type
        $entryTypeId = $this->insert([
            'collection_name' => $collection_name,
            'handle' => $handle,
            'title' => $title,
            'url_prefix' => $url_prefix,
            'entry_type_layout_id' => $entry_type_layout_id
        ]);

        if ($getObject) {
            return $this->findById($entryTypeId)->exec();
        }

        return $entryTypeId;
    }

    /**
     *
     * @param  EntryType   $entryType
     * @param  Collection  $data
     * @return bool
     * @throws EntryException
     *
     */
    private function _update(EntryType $entryType, Collection $data): bool
    {
        $handle = $data->get('handle');
        $title = $data->get('title');
        $url_prefix = $data->get('url_prefix');
        $update = [];

        if ($handle && $handle != $entryType->handle) {
            // TODO validate if handle is ok and different
        }
        if ($title) {
            $update['title'] = $title;
        }
        if ($url_prefix !== null) {
            $update['url_prefix'] = $url_prefix;
        }

        try {
            $this->updateOne(['_id' => $entryType->_id], [
                '$set' => $update
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(self::DATABASE_ERROR . PHP_EOL . $exception->getMessage());
        }

        return true;
    }

    private function _delete(string $entryTypeId, bool $hard = false): bool
    {
    }
}

//    /**
//     *
//     * Get or create an entryType by handle
//     *  nb: almost only for test
//     *
//     * @throws EntryException|DatabaseException
//     *
//     */
//    public function getOrCreateByHandle(Collection $data): EntryType|null
//    {
//        $handle = $data->get('handle') ?? '';
//        if (empty($handle)) {
//            throw new EntryException(self::HANDLE_MISSING_IN_COLLECTION);
//        }
//
//        $entryType = $this->getByHandle($handle);
//
//        if (!$entryType) {
//            $entryType = $this->_create($data, false);
//        }
//        return $entryType;
//    }
