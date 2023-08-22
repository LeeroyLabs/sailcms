<?php

namespace SailCMS;

use SailCMS\Models\Entry as EntryModel;
use SailCMS\Models\EntryType;

class Entry
{
    private EntryModel $model;
    private mixed $result;

    /**
     *
     * Set the model
     *
     * @param  string  $entryTypeHandle
     * @return self
     * @throws Errors\ACLException
     * @throws Errors\DatabaseException
     * @throws Errors\EntryException
     * @throws Errors\PermissionException
     *
     */
    public static function from(string $entryTypeHandle = EntryType::DEFAULT_HANDLE): self
    {
        $instance = new self();
        $instance->model = EntryType::getEntryModelByHandle($entryTypeHandle);
        return $instance;
    }

    /**
     *
     * Get an entry with an id
     *
     * @return self
     *
     */
    public static function withId(): self
    {

    }
}