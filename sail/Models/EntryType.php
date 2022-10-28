<?php

namespace SailCMS\Models;

use SailCMS\ACL;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;

class EntryType extends BaseModel
{
    const HANDLE_MISSING_IN_COLLECTION = "You must set the entry type handle in your data collection";
    const HANDLE_ALREADY_EXISTS = "Handle already exists";
    const TITLE_MISSING_IN_COLLECTION = "You must set the entry type title in your data collection";
    const CANNOT_CREATE_ENTRY_TYPE = "You don't have the right to create an entry type";

    // Fields
    public string $collectionName;
    public string $title;
    public string $handle;
    public string $urlPrefix;
    public string $entryTypeLayoutId;

    public function fields(bool $fetchAllFields = false): array
    {
        return [
            'title',
            'handle',
            'collectionName',
            'urlPrefix',
            'entryTypeLayoutId'
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
     * @param  Collection  $data
     * @return array|EntryType|null
     * @throws DatabaseException|EntryException|ACLException
     *
     */
    public function create(Collection $data): array|EntryType|null
    {
        $this->_hasPermission();

        return $this->_create($data);
    }

    /**
     *
     * Wrapper to handle permission for entry creation
     *
     * @param  Collection  $data
     * @return bool
     * @throws DatabaseException|EntryException|ACLException
     *
     */
    public function update(Collection $data): bool
    {
        $this->_hasPermission();

        return $this->_update($data);
    }

    /**
     *
     * @param  string  $entryTypeId
     * @return bool
     * @throws EntryException
     *
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
     * @throws EntryException
     *
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

    private function _hasPermission()
    {
        if (!ACL::hasPermission(User::$currentUser, ACL::write('entryType'))) {
            throw new EntryException(self::CANNOT_CREATE_ENTRY_TYPE);
        }
    }

    /**
     *
     * Create an entry type
     *
     * @param  Collection  $data
     * @param  bool        $checkIfHandleExists
     * @return array|EntryType|null
     * @throws EntryException|DatabaseException
     *
     */
    private function _create(Collection $data, bool $checkIfHandleExists = true): array|EntryType|null
    {
        $title = $data->get('title');
        $handle = $data->get('handle');
        $urlPrefix = $data->get('urlPrefix', '');
        $entryTypeLayoutId = $data->get('entryTypeLayoutId', null);

        // Create an empty layout
        if ($entryTypeLayoutId === null) {
            $entryTypeLayoutQs = new EntryTypeLayout();
            $entryTypeLayoutId = $entryTypeLayoutQs->createEmpty($title);
        }

        // Check everytime if the handle is already exists
        if ($checkIfHandleExists && $this->getByHandle($handle) !== null) {
            throw new EntryException(self::HANDLE_ALREADY_EXISTS);
        }

        // Create the entry type
        $entryTypeId = $this->insert([
            'handle' => $handle,
            'title' => $title,
            'url_prefix' => $urlPrefix,
            'entry_type_layout_id' => $entryTypeLayoutId
        ]);

        return $this->findById($entryTypeId)->exec();
    }

    private function _update(Collection $data): bool
    {
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
