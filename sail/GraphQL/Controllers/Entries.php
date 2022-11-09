<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Entry;
use SailCMS\Models\EntryType;

class Entries
{
    /**
     * Get all entry types
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Collection
     * @throws DatabaseException
     *
     */
    public function entryTypes(mixed $obj, Collection $args, Context $context): Collection
    {
        // TODO add pagination
        return EntryType::getAll();
    }

    /**
     * Get an entry type by id or by his handle
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return EntryType|null
     * @throws DatabaseException
     * @throws EntryException
     */
    public function entryType(mixed $obj, Collection $args, Context $context): ?EntryType
    {
        $id = $args->get('id');
        $handle = $args->get('handle');

        $result = null;
        if (!$id && !$handle) {
            $result = EntryType::getDefaultType();
        }

        if ($id) {
            $result = (new EntryType())->getById($id);
        }

        if ($handle) {
            $result = (new EntryType())->getByHandle($handle);
        }

        return $result;
    }

    /**
     *
     * Create entry type
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return EntryType
     * @throws DatabaseException
     * @throws EntryException
     * @throws ACLException
     *
     */
    public function createEntryType(mixed $obj, Collection $args, Context $context): EntryType
    {
        // string $handle, string $title, string $url_prefix, string|ObjectId|null $entry_type_layout_id = null
        $handle = $args->get('handle');
        $title = $args->get('title');
        $url_prefix = $args->get('url_prefix');
        $entry_type_layout_id = $args->get('entry_type_layout_id');

        return (new EntryType())->createOne($handle, $title, $url_prefix, $entry_type_layout_id);
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
     *
     */
    public function updateEntryType(mixed $obj, Collection $args, Context $context): bool
    {
        $handle = $args->get('handle');

        return (new EntryType())->updateByHandle($handle, $args);
    }

    /**
     *
     *
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    public function deleteEntryType(mixed $obj, Collection $args, Context $context): bool
    {
        $id = $args->get('id');

        return (new EntryType())->hardDelete($id);
    }

    /**
     *
     * Get all entries of all types
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Collection
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    public function entries(mixed $obj, Collection $args, Context $context): Collection
    {
        // TODO add pagination + do we class entries by entry types instead ?
        // TODO add performance options with context
        return Entry::getAll();
    }

    /**
     *
     * Get an entry by id (MUST TESTS)
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Entry|null
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    public function entry(mixed $obj, Collection $args, Context $context): ?Entry
    {
        $type = $args->get('typeHandle');
        $id = $args->get('id');

        if ($type) {
            $entryModel = EntryType::getEntryModelByHandle($type);
        } else {
            $entryModel = new Entry();
        }

        return $entryModel->one(['_id' => $id]);
    }

    public function createEntry(mixed $obj, Collection $args, Context $context): EntryType
    {
    }

    public function updateEntry(mixed $obj, Collection $args, Context $context): bool
    {
    }

    public function deleteEntry(mixed $obj, Collection $args, Context $context): bool
    {
    }
}