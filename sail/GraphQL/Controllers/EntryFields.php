<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\EntryField;

class EntryFields
{
    /**
     *
     * Get entry field by key
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return EntryField|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function entryField(mixed $obj, Collection $args, Context $context): ?EntryField
    {
        return EntryField::getByKey($args->get('key'));
    }

    /**
     *
     * Get entry field by id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return EntryField|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function entryFieldById(mixed $obj, Collection $args, Context $context): ?EntryField
    {
        return EntryField::getById($args->get('id'));
    }

    /**
     *
     * Get list of entry fields
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function entryFields(mixed $obj, Collection $args, Context $context): Collection
    {
        return (new EntryField())->getList();
    }

    /**
     *
     * Validate a key for entry field
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function entryFieldValidateKey(mixed $obj, Collection $args, Context $context): bool
    {
        return (new EntryField())->validateKey($args->get('key'));
    }

    /**
     *
     * Create an entry field
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return EntryField
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function createEntryField(mixed $obj, Collection $args, Context $context): EntryField
    {
        return (new EntryField())->create($args);
    }

    /**
     *
     * Update entry field
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws DatabaseException
     *
     */
    public function updateEntryField(mixed $obj, Collection $args, Context $context): bool
    {
        $id = $args->get('id');
        $args->offsetUnset('id');

        return (new EntryField())->update($id, $args);
    }

    /**
     *
     * Delete by id or key
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function deleteEntryField(mixed $obj, Collection $args, Context $context): bool
    {
        return (new EntryField())->deleteByIdOrKey($args->get('id'), $args->get('key'));
    }

    /**
     *
     * Delete entry fields by id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return int
     * @throws DatabaseException
     *
     */
    public function deleteEntryFields(mixed $obj, Collection $args, Context $context): int
    {
        $ids = $args->get('ids');

        if ($ids->length > 0) {
            return (new EntryField())->deleteManyByIds($ids);
        }
        return 0;
    }
}