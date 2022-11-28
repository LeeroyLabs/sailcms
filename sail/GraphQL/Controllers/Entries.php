<?php

namespace SailCMS\GraphQL\Controllers;

use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Entry;
use SailCMS\Models\EntryType;
use SailCMS\Sail;
use SailCMS\Types\Listing;
use SodiumException;

class Entries
{
    /**
     * Get all entry types
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function entryTypes(mixed $obj, Collection $args, Context $context): Collection
    {
        $result = EntryType::getAll(true);

        $result->each(function ($key, &$entryType) {
            if (!$entryType->entry_layout_id) {
                $entryType->entry_layout_id = "";
            }
        });

        return $result;
    }

    /**
     * Get an entry type by id or by his handle
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return EntryType|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
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

        // Valid and clean data before to send it
        if (!$result) {
            $msg = $id ? "id = " . $id : $handle;
            throw new EntryException(sprintf(EntryType::DOES_NOT_EXISTS, $msg));
        }

        if (!$result->entry_layout_id) {
            $result->entry_layout_id = "";
        }

        return $result;
    }

    /**
     *
     * Create entry type
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return EntryType
     * @throws DatabaseException
     * @throws EntryException
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public function createEntryType(mixed $obj, Collection $args, Context $context): EntryType
    {
        $handle = $args->get('handle');
        $title = $args->get('title');
        $urlPrefix = $args->get('url_prefix');
        $entryLayoutId = $args->get('entry_layout_id');

        $result = (new EntryType())->create($handle, $title, $urlPrefix, $entryLayoutId);

        if (!$result->entry_layout_id) {
            $result->entry_layout_id = "";
        }

        return $result;
    }

    /**
     *
     * Update an entry type by handle
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
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
     * Delete an entry type
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
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
     * Get all entries of a given type
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return Listing
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SodiumException
     */
    public function entries(mixed $obj, Collection $args, Context $context): Listing
    {
        // TODO add performance options with context
        $entryTypeHandle = $args->get('entry_type_handle');
        $page = $args->get('page', 1);
        $limit = $args->get('limit', 50);
        $sort = $args->get('sort', 'title');
        $direction = $args->get('direction', 1);

        // For filtering
        $siteId = $args->get('site_id', Sail::siteId());

        $homepage = Entry::getHomepage($siteId)?->{$siteId};

        $filters = [
            "site_id" => $siteId
        ];

        $result = Entry::getList($entryTypeHandle, $filters, $page, $limit, $sort, $direction); // By entry type instead
        $data = Collection::init();

        // Clean data before returning it.
        $result->list->each(function ($key, &$entry) use ($homepage, $data) {
            $entryArray = $entry->toArray($homepage);
            $data->push($entryArray);
        });

        return new Listing($result->pagination, $data);
    }

    /**
     *
     * Get an entry by id (MUST TESTS)
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return Entry|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SodiumException
     *
     */
    public function entry(mixed $obj, Collection $args, Context $context): ?array
    {
        $entryTypeHandle = $args->get('entry_type_handle');
        $id = $args->get('id');
        $siteId = $args->get("site_id", Sail::siteId());

        $homepage = Entry::getHomepage($siteId)?->{$siteId};

        $entryModel = $this->getEntryModelByHandle($entryTypeHandle);

        return $entryModel->one(['_id' => $id])->toArray($homepage);
    }

    /**
     *
     * Create an entry and return it
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return Entry|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     * @throws JsonException
     * @throws FilesystemException
     * @throws SodiumException
     *
     */
    public function createEntry(mixed $obj, Collection $args, Context $context): ?array
    {
        $isHomepage = $args->get('is_homepage');
        $entryTypeHandle = $args->get('entry_type_handle');
        $parent = $args->get('parent');
        $locale = $args->get('locale');
        $alternates = $args->get('alternates');
        $status = $args->get('status');
        $title = $args->get('title');
        $slug = $args->get('slug');
        $categories = $args->get('categories');
        $content = $args->get('content');
        $siteId = $args->get('site_id');

        $entryModel = $this->getEntryModelByHandle($entryTypeHandle);

        $entry = $entryModel->create($isHomepage, $locale, $status, $title, $slug, [
            'parent' => $parent,
            'alternates' => $alternates,
            'categories' => $categories,
            'content' => $content,
            'site_id' => $siteId
        ]);

        $homepage = Entry::getHomepage($siteId ?? Sail::siteId())->{$siteId ?? Sail::siteId()};

        return $entry->toArray($homepage);
    }

    /**
     *
     * Update an entry
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SodiumException
     *
     */
    public function updateEntry(mixed $obj, Collection $args, Context $context): bool
    {
        $id = $args->get('id');
        $entryTypeHandle = $args->get('entry_type_handle');

        $entryModel = $this->getEntryModelByHandle($entryTypeHandle);

        return $entryModel->updateById($id, $args);
    }

    /**
     *
     * Delete an entry
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SodiumException
     *
     */
    public function deleteEntry(mixed $obj, Collection $args, Context $context): bool
    {
        $id = $args->get('id');
        $entryTypeHandle = $args->get('entry_type_handle');
        $soft = $args->get('soft', true);
        $siteId = $args->get('site_id');

        $entryModel = $this->getEntryModelByHandle($entryTypeHandle);

        return $entryModel->delete($id, $siteId, $soft);
    }

    /**
     *
     * According to the given entry type handle return the Entry Model
     *  - if entry type handle is null, return the default entry type
     *
     * @param  ?string $entryTypeHandle
     * @return Entry
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    private function getEntryModelByHandle(?string $entryTypeHandle): Entry
    {
        if (isset($entryTypeHandle)) {
            $entryModel = EntryType::getEntryModelByHandle($entryTypeHandle);
        } else {
            $entryModel = new Entry();
        }
        return $entryModel;
    }
}