<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\EntryType;
use SailCMS\Types\LocaleField;

class EntryTypes
{
    /**
     * Get all entry types
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
    public function entryTypes(mixed $obj, Collection $args, Context $context): Collection
    {
        $result = EntryType::getAll(true);

        $parsedResult = Collection::init();
        $result->each(function ($key, &$entryType) use ($parsedResult) {
            /**
             * @var EntryType $entryType
             */
            $parsedResult->push($entryType->simplify());
        });

        return $parsedResult;
    }

    /**
     * Get an entry type by id or by his handle
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return array|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function entryType(mixed $obj, Collection $args, Context $context): ?array
    {
        $id = $args->get('id');
        $handle = $args->get('handle');

        $result = null;
        if (!$handle) {
            $result = EntryType::getDefaultType();
        }

        if ($handle) {
            $result = (new EntryType())->getByHandle($handle);
        }

        // Valid and clean data before to send it
        if (!$result) {
            $msg = $id ? "id = " . $id : $handle;
            throw new EntryException(sprintf(EntryType::DOES_NOT_EXISTS, $msg));
        }

        return $result->simplify();
    }

    /**
     *
     * Create entry type
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return array
     * @throws DatabaseException
     * @throws EntryException
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public function createEntryType(mixed $obj, Collection $args, Context $context): array
    {
        $handle = $args->get('handle');
        $title = $args->get('title');
        $urlPrefix = $args->get('url_prefix');
        $entryLayoutId = $args->get('entry_layout_id');
        $useCategories = $args->get('use_categories');

        $urlPrefix = new LocaleField($urlPrefix->unwrap());

        $result = (new EntryType())->create($handle, $title, $urlPrefix, $entryLayoutId, $useCategories);
        return $result->simplify();
    }

    /**
     *
     * Update an entry type by handle
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
    public function updateEntryType(mixed $obj, Collection $args, Context $context): bool
    {
        $handle = $args->get('handle');
        $urlPrefix = $args->get('url_prefix');

        // Override url_prefix to pass a LocaleField instead of a Collection
        if ($urlPrefix) {
            $args->pushKeyValue('url_prefix', new LocaleField($urlPrefix->unwrap()));
        }

        return (new EntryType())->updateByHandle($handle, $args);
    }

    /**
     *
     * Delete an entry type
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
    public function deleteEntryType(mixed $obj, Collection $args, Context $context): bool
    {
        $id = $args->get('id');

        return (new EntryType())->hardDelete($id);
    }
}