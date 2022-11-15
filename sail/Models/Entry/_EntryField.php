<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\FieldException;
use SailCMS\Errors\PermissionException;
use SailCMS\Models\EntryType;

class EntryField extends Field
{
    /* ERRORS */
    const ENTRY_DOES_NOT_EXISTS = 'Entry of %s type does not exists.';

    protected function defineSchema(): void
    {
        $this->schema = new Collection([
            'entry_id' => static::TYPE_STRING,
            'entry_type_handle' => static::TYPE_STRING
        ]);
    }

    protected function required(): array
    {
        return ['entry_id', 'entry_type_handle'];
    }

    /**
     *
     *
     *
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException|FieldException
     *
     */
    protected function validate(): void
    {
        $entryId = $this->content->get('entry_id');
        $entryTypeHandle = $this->content->get('entry_type_handle');

        $entryModel = EntryType::getEntryModelByHandle($entryTypeHandle);
        if ($entryModel->getCount(['_id' => $entryId]) != 1) {
            throw new FieldException(sprintf(static::ENTRY_DOES_NOT_EXISTS, $entryTypeHandle));
        }
    }
}