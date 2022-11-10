<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
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
     *
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
     * @throws PermissionException
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
     * @throws PermissionException
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
     * @throws PermissionException
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
        $entry_type_handle = $args->get('entry_type_handle');
        $id = $args->get('id');

        $entryModel = $this->getEntryModelByHandle($entry_type_handle);

        return $entryModel->one(['_id' => $id]);
    }

    /**
     *
     * Create an entry and return it
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Entry|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function createEntry(mixed $obj, Collection $args, Context $context): ?Entry
    {
        $entry_type_handle = $args->get('entry_type_handle');
        $locale = $args->get('locale');
        $status = $args->get('status');
        $title = $args->get('title');
        $slug = $args->get('slug');
        $categories = $args->get('categories');
        $content = $args->get('content');
        // TODO support these fields
        $parent_id = $args->get('parent_id');
        $site_id = $args->get('site_id');
        $alternates = $args->get('alternates');

        $entryModel = $this->getEntryModelByHandle($entry_type_handle);

        $entry = $entryModel->createOne($locale, $status, $title, $slug, [
            'categories' => $categories,
            'content' => $content
        ]);


        return $entry;
    }

    /**
     *
     * Update an entry
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
    public function updateEntry(mixed $obj, Collection $args, Context $context): bool
    {
        $id = $args->get('id');
        $entry_type_handle = $args->get('entry_type_handle');

        $entryModel = $this->getEntryModelByHandle($entry_type_handle);

        return $entryModel->updateById($id, $args);
    }

    /**
     *
     * Delete an entry
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
    public function deleteEntry(mixed $obj, Collection $args, Context $context): bool
    {
        $id = $args->get('id');
        $entry_type_handle = $args->get('entry_type_handle');
        $soft = $args->get('soft', true);

        $entryModel = $this->getEntryModelByHandle($entry_type_handle);

        return $entryModel->delete($id, $soft);
    }

    /**
     *
     * According to the given entry type handle return the Entry Model
     *  - if entry type handle is null, return the default entry type
     *
     * @param  ?string  $entry_type_handle
     * @return Entry
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    private function getEntryModelByHandle(?string $entry_type_handle): Entry
    {
        if (isset($entry_type_handle)) {
            $entryModel = EntryType::getEntryModelByHandle($entry_type_handle);
        } else {
            $entryModel = new Entry();
        }
        return $entryModel;
    }
}