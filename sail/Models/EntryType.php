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
    // Fields
    public string $collectionName;

    public function fields(bool $fetchAllFields = false): array
    {
        return [];
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
}



//
//namespace SailCMS\Models;
//
//use SailCMS\ACL;
//use SailCMS\Collection;
//use SailCMS\Database\BaseModel;
//use SailCMS\Errors\ACLException;
//use SailCMS\Errors\DatabaseException;
//use SailCMS\Errors\EntryException;
//
//<<<<<<< HEAD
//
///**
// * TODO add homepage flag
// *  check url
// */
//=======
//>>>>>>> 41113330b71df5a1e159c21718212c9ee64e6928
//class EntryType extends BaseModel
//{
//    // Fields
//    public string $collectionName;
//
//    public function fields(bool $fetchAllFields = false): array
//    {
//        return [];
//    }
//
//    /**
//     *
//<<<<<<< HEAD
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
//
//    public function getById(string $entryTypeId): EntryType|null
//    {
//    }
//
//    /**
//     *
//     * Get an entryType by handle
//     *
//     * @param  string  $handle
//     * @return EntryType|null
//     *
//     */
//    public function getByHandle(string $handle): EntryType|null
//    {
//        try {
//            $result = $this->findOne(['handle' => $handle])->exec();
//        } catch (DatabaseException $exception) {
//            $result = null;
//            // TODO Log instead of fail silently ???
//        }
//        return $result;
//    }
//
//    public function all($limit, $offset): EntryType|null
//    {
//    }
//
//    /**
//     *
//     * Wrapper to handle permission for entry creation
//     *
//     * @param  Collection  $data
//     * @return array|EntryType|null
//     * @throws DatabaseException|EntryException|ACLException
//     *
//     */
//    public function create(Collection $data): array|EntryType|null
//    {
//        if (!ACL::hasPermission(User::$currentUser, ACL::write('entryType'))) {
//            throw new EntryException(self::CANNOT_CREATE_ENTRY_TYPE);
//        }
//
//        return $this->_create($data);
//    }
//
//    public function update(Collection $data): bool
//    {
//        if (!ACL::hasPermission(User::$currentUser, ACL::write('entryType'))) {
//            throw new EntryException(self::CANNOT_CREATE_ENTRY_TYPE);
//        }
//
//        return $this->_update($data);
//    }
//
//    public function softDelete(string $entryTypeId): bool
//    {
//        // TODO do we will have delete permission ?
//        if (!ACL::hasPermission(User::$currentUser, ACL::write('entryType'))) {
//            throw new EntryException(self::CANNOT_CREATE_ENTRY_TYPE);
//        }
//
//        return $this->_delete($entryTypeId);
//    }
//
//    public function hardDelete(string $entryTypeId): bool
//    {
//        // TODO do we will have delete permission ?
//        if (!ACL::hasPermission(User::$currentUser, ACL::write('entryType'))) {
//            throw new EntryException(self::CANNOT_CREATE_ENTRY_TYPE);
//        }
//
//        return $this->_delete($entryTypeId, true);
//    }
//
//    /**
//     *
//     * Create an entry type
//     *
//     * @param  Collection  $data
//     * @param  bool        $checkIfHandleExists
//     * @return array|EntryType|null
//     * @throws EntryException|DatabaseException
//=======
//     * Get a list of all available types
//     *
//     * @return Collection
//     * @throws DatabaseException
//>>>>>>> 41113330b71df5a1e159c21718212c9ee64e6928
//     *
//     */
//    public static function getAll(): Collection
//    {
//        $instance = new static();
//        return new Collection($instance->find([])->exec());
//    }
//
//
//
//<<<<<<< HEAD
//    private function _update(Collection $data): bool
//    {
//    }
//
//    private function _delete(string $entryTypeId, bool $hard = false): bool
//    {
//    }
//
//    /**
//     *
//     * Validation on store
//     *
//     * @throws EntryException
//     *
//     */
//    protected function processOnStore(string $field, mixed $value): mixed
//    {
//        // Data verification
//        if ($field == "handle" && empty($value)) {
//            throw new EntryException(self::HANDLE_MISSING_IN_COLLECTION);
//        }
//        if ($field == "title" && empty($value)) {
//            throw new EntryException(self::TITLE_MISSING_IN_COLLECTION);
//        }
//=======
////    private const HANDLE_MISSING_IN_COLLECTION = "You must set the entry type handle in your data collection";
////    private const HANDLE_ALREADY_EXISTS = "Handle already exists";
////    private const TITLE_MISSING_IN_COLLECTION = "You must set the entry type title in your data collection";
////    private const CANNOT_CREATE_ENTRY_TYPE = "You don't have the right to create an entry type";
////
////    public string $title;
////    public string $handle;
////    public string $urlPrefix;
////    public string $entryTypeLayoutId;
////
////    public function fields(bool $fetchAllFields = false): array
////    {
////        return ['_id', 'title', 'handle', 'urlPrefix'];
////    }
////
////    /**
////     *
////     * Get or create an entryType by handle
////     *  nb: almost only for test
////     *
////     * @throws EntryException|DatabaseException
////     *
////     */
////    public function getOrCreateByHandle(Collection $data): EntryType|null
////    {
////        $handle = $data->get('handle') ?? '';
////        if (empty($handle)) {
////            throw new EntryException(self::HANDLE_MISSING_IN_COLLECTION);
////        }
////
////        $entryType = $this->getByHandle($handle);
////
////        if (!$entryType) {
////            $entryType = $this->_create($data, false);
////        }
////        return $entryType;
////    }
////
////    /**
////     *
////     * Get an entryType by handle
////     *
////     * @param  string  $handle
////     * @return EntryType|null
////     *
////     */
////    public function getByHandle(string $handle): EntryType|null
////    {
////        try {
////            $result = $this->findOne(['handle' => $handle])->exec();
////        } catch (DatabaseException $exception) {
////            $result = null;
////            // TODO Log instead of fail silently ???
////        }
////        return $result;
////    }
////
////    /**
////     *
////     * Wrapper to handle permission for entry creation
////     *
////     * @param  Collection  $data
////     * @return array|EntryType|null
////     * @throws DatabaseException
////     * @throws EntryException
////     * @throws ACLException
////     *
////     */
////    public function createFromAPI(Collection $data): array|EntryType|null
////    {
////        if (!ACL::hasPermission(User::$currentUser, ACL::write('entryType'))) {
////            throw new EntryException(self::CANNOT_CREATE_ENTRY_TYPE);
////        }
////
////        return $this->_create($data);
////    }
////
////    /**
////     *
////     * Create an entry type
////     *
////     * @param  Collection  $data
////     * @param  bool        $checkIfHandleExists
////     * @return array|EntryType|null
////     * @throws EntryException|DatabaseException
////     *
////     */
////    private function _create(Collection $data, bool $checkIfHandleExists = true): array|EntryType|null
////    {
////        $title = $data->get('title');
////        $handle = $data->get('handle');
////        $urlPrefix = $data->get('urlPrefix', '');
////        $entryTypeLayoutId = $data->get('entryTypeLayoutId', null);
////
////        // Create an empty layout
////        if ($entryTypeLayoutId === null) {
////            $entryTypeLayoutQs = new EntryTypeLayout();
////            $entryTypeLayoutId = $entryTypeLayoutQs->createEmpty($title);
////        }
////
////        // Check everytime if the handle is already exists
////        if ($checkIfHandleExists && $this->getByHandle($handle) !== null) {
////            throw new EntryException(self::HANDLE_ALREADY_EXISTS);
////        }
////
////        // Create the entry type
////        $entryTypeId = $this->insert([
////            'handle' => $handle,
////            'title' => $title,
////            'url_prefix' => $urlPrefix,
////            'entry_type_layout_id' => $entryTypeLayoutId
////        ]);
////
////        return $this->findById($entryTypeId)->exec();
////    }
////
////    /**
////     *
////     * Validation on store
////     *
////     * @throws EntryException
////     *
////     */
////    protected function processOnStore(string $field, mixed $value): mixed
////    {
////        // Data verification
////        if ($field === "handle" && empty($value)) {
////            throw new EntryException(self::HANDLE_MISSING_IN_COLLECTION);
////        }
////        if ($field === "title" && empty($value)) {
////            throw new EntryException(self::TITLE_MISSING_IN_COLLECTION);
////        }
////
////        return parent::processOnStore($field, $value);
////    }
//>>>>>>> 41113330b71df5a1e159c21718212c9ee64e6928
//
//}