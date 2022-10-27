<?php

namespace SailCMS\Models;

use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;


/**
 * TODO add homepage flag
 * check url
 */
class EntryType extends BaseModel
{
    const HANDLE_MISSING_IN_COLLECTION = "You must set the entry type handle in your data collection";
    const HANDLE_ALREADY_EXISTS = "Handle already exists";
    const TITLE_MISSING_IN_COLLECTION = "You must set the entry type title in your data collection";
    const CANNOT_CREATE_ENTRY_TYPE = "You don't have the right to create an entry type";

    public string $title;
    public string $handle;
    public string $urlPrefix;
    public string $entryTypeLayoutId;

    public function fields(bool $fetchAllFields = false): array
    {
        return ['_id', 'title', 'handle', 'urlPrefix'];
    }

    /**
     *
     * Get or create an entryType by handle
     *  nb: almost only for test
     *
     * @throws EntryException|DatabaseException
     *
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
     *
     * Get an entryType by handle
     *
     * @param  string  $handle
     * @return EntryType|null
     *
     */
    public function getByHandle(string $handle): EntryType|null
    {
        try {
            $result = $this->findOne(['handle' => $handle])->exec();
        } catch (DatabaseException $exception) {
            $result = null;
            // TODO Log instead of fail silently ???
        }
        return $result;
    }

    /**
     * Wrapper to handle permission for entry creation
     *
     * @param  Collection  $data
     * @return array|EntryType|null
     * @throws DatabaseException
     * @throws EntryException
     */
    public function createFromAPI(Collection $data): array|EntryType|null
    {
        if (!ACL::hasPermission(User::$currentUser, ACL::write('entryType'))) {
            throw new EntryException(self::CANNOT_CREATE_ENTRY_TYPE);
        }

        return $this->_create($data);
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
        if ($field == "handle" && empty($value)) {
            throw new EntryException(self::HANDLE_MISSING_IN_COLLECTION);
        }
        if ($field == "title" && empty($value)) {
            throw new EntryException(self::TITLE_MISSING_IN_COLLECTION);
        }

        return parent::processOnStore($field, $value);
    }
}