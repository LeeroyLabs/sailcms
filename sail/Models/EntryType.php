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
    public string $title;
    public string $handle;
    public string $urlPrefix;

    const HANDLE_MISSING_IN_COLLECTION = "You must set the entry type handle in your collection";
    const HANDLE_ALREADY_EXISTS = "Handle already exists";
    const TITLE_MISSING_IN_COLLECTION = "You must set the entry type title in your collection";

    public function fields(bool $fetchAllFields = false): array
    {
        return ['_id', 'title', 'handle', 'urlPrefix'];
    }

    /**
     * Get or create an entryType by handle
     *
     * @throws EntryException|DatabaseException
     */
    public function getOrCreateByHandle(Collection $data): EntryType|null
    {
        $handle = $data->get('handle') ?? '';
        if (empty($handle)) {
            throw new EntryException(self::HANDLE_MISSING_IN_COLLECTION);
        }

        $entryType = $this->getByHandle($handle);

        if (!$entryType) {
            $entryType = $this->_create($data, false);
        }
        return $entryType;
    }

    /**
     * Get an entryType by handle
     *
     * @param  string  $handle
     * @return EntryType|null
     */
    public function getByHandle(string $handle):EntryType|null
    {
        try
        {
            $result = $this->findOne(['handle' => $handle])->exec();
        }
        catch (DatabaseException $exception)
        {
            $result = null;
            // TODO Log instead of fail silently ???
        }
        return $result;
    }

    /**
     * Create an entry type
     *
     * @param  Collection $data
     * @param  bool $checkIfHandleExists
     * @return mixed|string|void
     * @throws EntryException|DatabaseException
     */
    private function _create(Collection $data, bool $checkIfHandleExists=true)
    {
// TODO Handle permissions
//        if (!ACL::hasPermission(User::$currentUser, ACL::write('entryType'))) {
//            return null; create an exception file where ?
//        }

        $title = $data->get('title');
        $handle = $data->get('handle');
        $urlPrefix = $data->get('urlPrefix') ?? '';

        if ($handle == null) {
            throw new EntryException(self::HANDLE_MISSING_IN_COLLECTION);
        }
        if ($title == null) {
            throw new EntryException(self::TITLE_MISSING_IN_COLLECTION);
        }

        // Check everytime if the handle is already exists
        if ($checkIfHandleExists && $this->getByHandle($handle) !== null) {
            throw new EntryException(self::HANDLE_ALREADY_EXISTS);
        }

        $entryTypeId = $this->insert([
            'handle' => $handle,
            'title' => $title,
            'url_prefix' => $urlPrefix
        ]);

        return $this->findById($entryTypeId)->exec();
    }
}