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
     * Create an entry field
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return array
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    public function createEntryField(mixed $obj, Collection $args, Context $context): array
    {
        $entryFieldModel = new EntryField();

        /**
         * @var EntryField $entryField
         */
        $entryField = $entryFieldModel->castTo($args);

        if (!$entryField->save()) {
            throw new EntryException("Could not create entry field");
        }

        return $entryField->castFrom();
    }
}