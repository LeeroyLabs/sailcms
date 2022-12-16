<?php

namespace SailCMS\GraphQL\Controllers;

use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\FieldException;
use SailCMS\Errors\PermissionException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Entry;
use SailCMS\Models\EntryLayout;
use SailCMS\Models\EntryType;
use SailCMS\Sail;
use SailCMS\Types\Listing;
use SailCMS\Types\LocaleField;
use SodiumException;

class Entries
{
    /**
     *
     * Get the home page entry
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
    public function homepageEntry(mixed $obj, Collection $args, Context $context): ?array
    {
        $locale = $args->get('locale');
        $siteId = $args->get('site_id');

        return Entry::getHomepageEntry($siteId, $locale, true);
    }

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

        $parsedResult = Collection::init();
        $result->each(function ($key, &$entryType) use ($parsedResult) {
            $parsedResult->push($entryType->toGraphQL());
        });

        return $parsedResult;
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
    public function entryType(mixed $obj, Collection $args, Context $context): ?array
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

        return $result->toGraphQL();
    }

    /**
     *
     * Create entry type
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
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

        $urlPrefix = new LocaleField($urlPrefix->unwrap());

        $result = (new EntryType())->create($handle, $title, $urlPrefix, $entryLayoutId);

        if (!$result->entry_layout_id) {
            $result->entry_layout_id = "";
        }

        return $result->toGraphQL();
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
        $entryTypeHandle = $args->get('entry_type_handle');
        $page = $args->get('page', 1);
        $limit = $args->get('limit', 50);
        $sort = $args->get('sort', 'title');
        $direction = $args->get('direction', 1);

        // For filtering
        $siteId = $args->get('site_id', Sail::siteId());

        $currentSiteHomepages = Entry::getHomepage($siteId);

        $filters = [
            "site_id" => $siteId
        ];

        $result = Entry::getList($entryTypeHandle, $filters, $page, $limit, $sort, $direction); // By entry type instead
        $data = Collection::init();

        // Clean data before returning it.
        $result->list->each(function ($key, &$entry) use ($currentSiteHomepages, $data) {
            /**
             * @var Entry $entry
             */
            $homepage = $currentSiteHomepages->{$entry->locale} ?? null;
            $entryArray = $entry->toGraphQL($homepage);
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

        $entryModel = $this->getEntryModelByHandle($entryTypeHandle);
        $entry = $entryModel->one(['_id' => $id]);

        $homepage = Entry::getHomepage($siteId, $entry->locale);
        return $entry->toGraphQL($homepage);
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
        $content = Entry::processContentFromGraphQL($args->get('content'));
        $siteId = $args->get('site_id');

        $entryModel = $this->getEntryModelByHandle($entryTypeHandle);

        $entryOrErrors = $entryModel->create($isHomepage, $locale, $status, $title, $slug, [
            'parent' => $parent,
            'alternates' => $alternates,
            'categories' => $categories,
            'content' => $content,
            'site_id' => $siteId
        ]);

        $homepage = Entry::getHomepage($entryOrErrors->site_id, $entryOrErrors->locale);

        $result = [
            'entry' => [],
            'errors' => []
        ];
        if ($entryOrErrors instanceof Entry) {
            $result['entry'] = $entryOrErrors->toGraphQL($homepage);
        } else {
            $result['errors'] = $entryOrErrors;
        }

        return $result;
    }

    /**
     *
     * Update an entry
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SodiumException
     * @throws PermissionException
     *
     */
    public function updateEntry(mixed $obj, Collection $args, Context $context): Collection
    {
        $id = $args->get('id');
        $entryTypeHandle = $args->get('entry_type_handle');
        $content = $args->get('content');

        if ($content) {
            // Process the content to be able to save it
            $args->pushKeyValue('content', Entry::processContentFromGraphQL($content));
        }

        $entryModel = $this->getEntryModelByHandle($entryTypeHandle);

        $errors = $entryModel->updateById($id, $args, false);

        return Entry::processErrorsForGraphQL($errors);
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

        $entryModel = $this->getEntryModelByHandle($entryTypeHandle);

        return $entryModel->delete($id, $soft);
    }

    /**
     *
     * Get an entry layout by id
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return array|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function entryLayout(mixed $obj, Collection $args, Context $context): ?array
    {
        $entryLayoutId = $args->get('id');

        $entryLayoutModel = new EntryLayout();
        $entryLayout = $entryLayoutModel->one([
            '_id' => $entryLayoutId
        ]);

        return $entryLayout?->toGraphQL();
    }

    /**
     *
     * Get all entry layouts
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return array|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function entryLayouts(mixed $obj, Collection $args, Context $context): ?array
    {
        $entryLayouts = Collection::init();
        $result = (new EntryLayout())->getAll() ?? [];

        (new Collection($result))->each(function ($key, $entryLayout) use ($entryLayouts) {
            /**
             * @var EntryLayout $entryLayout
             */
            $entryLayouts->push($entryLayout->toGraphQL());
        });

        return $entryLayouts->unwrap();
    }

    /**
     *
     * Create an entry layout
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return array|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     * @throws FieldException
     *
     */
    public function createEntryLayout(mixed $obj, Collection $args, Context $context): ?array
    {
        $titles = $args->get('titles');
        $graphqlSchema = $args->get('schema');

        $titles = new LocaleField($titles->unwrap());

        $schema = EntryLayout::processSchemaFromGraphQL($graphqlSchema);
        $generatedSchema = EntryLayout::generateLayoutSchema($schema);

        $entryLayoutModel = new EntryLayout();
        $entryLayout = $entryLayoutModel->create($titles, $generatedSchema);

        return $entryLayout->toGraphQL();
    }

    /**
     *
     * Update an entry layout
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
    public function updateEntryLayoutSchema(mixed $obj, Collection $args, Context $context): bool
    {
        $id = $args->get('id');
        $titles = $args->get('titles');
        $schemaUpdate = $args->get('schema_update');

        $entryLayoutModel = new EntryLayout();
        $entryLayout = $entryLayoutModel->one(['_id' => $id]);

        if (!$entryLayout) {
            throw new EntryException(sprintf(EntryLayout::DOES_NOT_EXISTS, $id));
        }

        EntryLayout::updateSchemaFromGraphQL($schemaUpdate, $entryLayout);

        return $entryLayoutModel->updateById($id, $titles, $entryLayout->schema);
    }

    /**
     *
     * Update a key in an entry layout schema
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
    public function updateEntryLayoutSchemaKey(mixed $obj, Collection $args, Context $context): bool
    {
        $id = $args->get('id');
        $key = $args->get('key');
        $newKey = $args->get('newKey');

        $entryLayoutModel = new EntryLayout();
        $entryLayout = $entryLayoutModel->one(['_id' => $id]);

        if (!$entryLayout) {
            throw new EntryException(sprintf(EntryLayout::DOES_NOT_EXISTS, $id));
        }

        return $entryLayout->updateSchemaKey($key, $newKey);
    }

    /**
     *
     * Delete an entry layout
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
    public function deleteEntryLayout(mixed $obj, Collection $args, Context $context): bool
    {
        $id = $args->get('id');
        $soft = $args->get('soft', true);

        $entryLayoutModel = new EntryLayout();

        return $entryLayoutModel->delete($id, $soft);
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