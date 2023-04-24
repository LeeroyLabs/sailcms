<?php

namespace SailCMS\GraphQL\Controllers;

use GraphQL\Type\Definition\ResolveInfo;
use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\CollectionException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\FieldException;
use SailCMS\Errors\PermissionException;
use SailCMS\Field;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Category;
use SailCMS\Models\Entry;
use SailCMS\Models\EntryLayout;
use SailCMS\Models\EntryPublication;
use SailCMS\Models\EntrySeo;
use SailCMS\Models\EntryType;
use SailCMS\Models\EntryVersion;
use SailCMS\Models\User;
use SailCMS\Sail;
use SailCMS\Types\Listing;
use SailCMS\Types\LocaleField;
use SailCMS\Types\SocialMeta;
use SodiumException;

class Entries
{
    /**
     *
     * Get the home page entry
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return array|null
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

        $urlPrefix = new LocaleField($urlPrefix->unwrap());

        $result = (new EntryType())->create($handle, $title, $urlPrefix, $entryLayoutId);

        if (!$result->entry_layout_id) {
            $result->entry_layout_id = "";
        }

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

    /**
     *
     * Get all entries of a given type
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Listing
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SodiumException
     *
     */
    public function entries(mixed $obj, Collection $args, Context $context): Listing
    {
        $entryTypeHandle = $args->get('entry_type_handle');
        $page = $args->get('page', 1);
        $limit = $args->get('limit', 50);
        $sort = $args->get('sort', 'title');
        $direction = $args->get('direction', 1);
        $search = $args->get('search', '');
        $ignoreTrash = $args->get('ignore_trash', true);

        // Get the result!
        $result = Entry::getList($entryTypeHandle, $search, $page, $limit, $sort, $direction, $ignoreTrash); // By entry type instead

        // Get homepage to set is_homepage on each entry
        $currentSiteHomepages = Entry::getHomepage(Sail::siteId());

        // Clean data before returning it.
        $data = Collection::init();
        $result->list->each(function ($key, &$entry) use ($currentSiteHomepages, &$data) {
            /**
             * @var Entry $entry
             */
            $homepage = $currentSiteHomepages->{$entry->locale} ?? null;
            $entryArray = $this->parseEntry($entry->simplify($homepage));
            $data->push($entryArray);
        });

        return new Listing($result->pagination, $data);
    }

    /**
     *
     * Get an entry by id (MUST TESTS)
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return array|null
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

        return $this->parseEntry($entry->simplify($homepage));
    }

    /**
     *
     * Get an entry by url
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return array|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SodiumException
     * @throws CollectionException
     *
     */
    public function entryByUrl(mixed $obj, Collection $args, Context $context): ?array
    {
        $url = $args->get('url');
        $siteId = $args->get("site_id", Sail::siteId());

        $entry = Entry::findByURL($url, $siteId);

        if ($entry) {
            $homepage = Entry::getHomepage($siteId, $entry->locale);
            return $this->parseEntry($entry->simplify($homepage));
        }
        return null;
    }

    /**
     *
     * Create an entry and return it
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return array|null
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
        $title = $args->get('title');
        $template = $args->get('template');
        $slug = $args->get('slug');
        $categories = $args->get('categories');
        $content = Entry::processContentFromGraphQL($args->get('content'));
        $siteId = $args->get('site_id');

        $entryModel = $this->getEntryModelByHandle($entryTypeHandle);

        $entryOrErrors = $entryModel->create($isHomepage, $locale, $title, $template, $slug, [
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
            $result['entry'] = $this->parseEntry($entryOrErrors->simplify($homepage));
        } else {
            $result['errors'] = $entryOrErrors;
        }

        return $result;
    }

    /**
     *
     * Update an entry
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
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
        $bypassValidation = $args->get('bypass_validation', false);

        $entryModel = $this->getEntryModelByHandle($entryTypeHandle);

        if ($content) {
            // Process the content to be able to save it
            $processedContent = Entry::processContentFromGraphQL($content);
            $newContent = $entryModel->updateContentForGraphQL($id, $processedContent);
            $args->pushKeyValue('content', $newContent);
        }

        $errors = $entryModel->updateById($id, $args, false, $bypassValidation);

        return Entry::processErrorsForGraphQL($errors);
    }

    /**
     *
     * Update entry SEO
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
    public function updateEntrySeo(mixed $obj, Collection $args, Context $context): bool
    {
        $entryId = $args->get('entry_id');
        $title = $args->get('title');
        $socialMetas = $args->get('social_metas');

        $entrySeoModel = new EntrySeo();
        if (!$title) {
            $seo = $entrySeoModel->getOrCreateByEntryId($entryId, "");
            $title = $seo->title;
        }

        // Parse social metas
        if ($socialMetas) {
            $parsedSocialMetas = Collection::init();
            $socialMetaInstance = new SocialMeta('');
            foreach ($socialMetas as $socialMeta) {
                $contentParsed = [];
                foreach ($socialMeta['content'] as $content) {
                    $contentParsed[$content['name']] = $content['content'];
                }
                $socialMeta->content = (object)$contentParsed;
                $parsedSocialMetas->push($socialMetaInstance->castTo($socialMeta));
            }
            // Override social metas to send SocialMeta classes
            $args->pushKeyValue('social_metas', $parsedSocialMetas);
        }

        $entrySeo = $entrySeoModel->createOrUpdate($entryId, $title, $args);

        return isset($entrySeo->entry_id);
    }

    /**
     *
     * Publish an entry
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return string
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SodiumException
     *
     */
    public function publishEntry(mixed $obj, Collection $args, Context $context): string
    {
        $entryId = $args->get('id');
        $publicationDate = $args->get('publication_date', 0);
        $expirationDate = $args->get('expiration_date', 0);
        $siteId = $args->get('site_id');

        return (new Entry())->publish($entryId, $publicationDate, $expirationDate, $siteId);
    }

    /**
     *
     * Unpublish an entry
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
    public function unpublishEntry(mixed $obj, Collection $args, Context $context): bool
    {
        $entryId = $args->get('id');

        return (new Entry())->unpublish($entryId);
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
     * Resolver for entry model
     *
     * @param  mixed        $obj
     * @param  Collection   $args
     * @param  Context      $context
     * @param  ResolveInfo  $info
     * @return mixed
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SodiumException
     *
     */
    public function entryResolver(mixed $obj, Collection $args, Context $context, ResolveInfo $info): mixed
    {
        // For EntryVersion
        if (!isset($obj['current'])) {
            if ($info->fieldName === "content") {
                // Get entry type then fake an entry object to use getContent to parse the content with the layout schema
                $entryType = $obj['entry_type'] ?? null;

                // This code will be deprecated, since there was a change in the simplify method in Entry.
                if (!$entryType) {
                    $entry_type_id = $obj['entry_type_id'];
                    $entryType = (new EntryType())->getById($entry_type_id);
                    $entryModel = $entryType->getEntryModel($entryType);
                } else {
                    $entryModel = EntryType::getEntryModelByHandle($entryType->handle);
                }
                $entryModel->content = new Collection((array)$obj['content']);
                return $entryModel->getContent();
            }
            return $obj[$info->fieldName];
        }

        /**
         * @var Entry $entry
         */
        $entry = $obj['current'];

        // Entry fields to resolve
        if ($info->fieldName === "content") {
            return $entry->getContent();
        }

        if ($info->fieldName === "schema") {
            return $entry->getSchema(true, true)->unwrap();
        }

        if ($info->fieldName === "seo") {
            return $entry->getSimplifiedSEO();
        }

        if ($info->fieldName === "publication") {
            return (new EntryPublication())->getPublicationByEntryId($entry->_id, false);
        }

        if ($info->fieldName === "parent") {
            if (!$obj['parent']['handle']) {
                return null;
            }
            $parent = $entry->getParent();
            $parentHomepage = Entry::getHomepage($parent->site_id, $parent->locale);
            return $parent->simplify($parentHomepage);
        }

        if ($info->fieldName === "categories") {
            $categoryIds = $obj['categories'];

            $categories = Category::getByIds($categoryIds);

            foreach ($categories as &$category) {
                /**
                 * @var Category $category
                 */
                $category = $category->simplify();
            }

            return $categories;
        }

        return $obj[$info->fieldName];
    }

    /**
     *
     *
     *
     * @throws DatabaseException
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public function entryAlternateResolver(mixed $obj, Collection $args, Context $context, ResolveInfo $info): mixed
    {
        if ($info->fieldName === "url") {
            $publication = (new EntryPublication())->getPublicationByEntryId($obj->entry_id);
            return $publication->entry_url ?? ""; // We default to an empty string in case the entry is not published
        }

        return $obj->{$info->fieldName};
    }

    /**
     *
     * Resolver authors of an entry
     *
     * @param  mixed        $obj
     * @param  Collection   $args
     * @param  Context      $context
     * @param  ResolveInfo  $info
     * @return mixed
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function authorsResolver(mixed $obj, Collection $args, Context $context, ResolveInfo $info): mixed
    {
        $userId = $obj[$info->fieldName] ?? null;

        if ($userId) {
            return (new User())->getById($userId);
        }
        return null;
    }

    /**
     *
     * Resolver the version for Entry Publication
     *
     * @param  mixed        $obj
     * @param  Collection   $args
     * @param  Context      $context
     * @param  ResolveInfo  $info
     * @return mixed
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function entryPublicationResolver(mixed $obj, Collection $args, Context $context, ResolveInfo $info): mixed
    {
        /**
         * @var EntryPublication $obj
         */
        if ($info->fieldName === "version") {
            return (new EntryVersion())->getById($obj->entry_version_id);
        }

        return $obj->{$info->fieldName};
    }


    /**
     *
     * Get entry version by id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return EntryVersion
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function entryVersion(mixed $obj, Collection $args, Context $context): EntryVersion
    {
        $id = $args->get('id');

        return (new EntryVersion())->getById($id);
    }

    /**
     *
     * Get entry versions by entry_id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return array
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function entryVersions(mixed $obj, Collection $args, Context $context): array
    {
        $entryId = $args->get('entry_id');

        return (new EntryVersion())->getVersionsByEntryId($entryId);
    }

    /**
     *
     * Apply entry version with entry version id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
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
    public function applyVersion(mixed $obj, Collection $args, Context $context): bool
    {
        $entry_version_id = $args->get('entry_version_id');

        return (new EntryVersion())->applyVersion($entry_version_id);
    }

    /**
     *
     * Get an entry layout by id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
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

        return $entryLayout?->simplify();
    }

    /**
     *
     * Get all entry layouts
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
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
            $entryLayouts->push($entryLayout->simplify());
        });

        return $entryLayouts->unwrap();
    }

    /**
     *
     * Create an entry layout
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
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
        $slug = $args->get('slug');

        $titles = new LocaleField($titles->unwrap());

        $schema = EntryLayout::processSchemaFromGraphQL($graphqlSchema);
        $generatedSchema = EntryLayout::generateLayoutSchema($schema);

        $entryLayoutModel = new EntryLayout();
        $entryLayout = $entryLayoutModel->create($titles, $generatedSchema, $slug);
        return $entryLayout->simplify();
    }

    /**
     *
     * Update an entry layout
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
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     * @throws JsonException
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
    public function deleteEntryLayout(mixed $obj, Collection $args, Context $context): bool
    {
        $id = $args->get('id');
        $soft = $args->get('soft', true);

        $entryLayoutModel = new EntryLayout();

        return $entryLayoutModel->delete($id, $soft);
    }

    /**
     *
     * Get all fields Info
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Collection
     *
     */
    public function fields(mixed $obj, Collection $args, Context $context): Collection
    {
        return Field::getAvailableFields();
    }

    /**
     *
     * According to the given entry type handle return the Entry Model
     *  - if entry type handle is null, return the default entry type
     *
     * @param  ?string  $entryTypeHandle
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

    /**
     *
     * Parse simplified entry for graphQL
     *
     * @param  array  $simplifiedEntry
     * @return array
     */
    private function parseEntry(array $simplifiedEntry): array
    {
        // Override SEO social metas
        if (isset($simplifiedEntry['seo']) && isset($simplifiedEntry['seo']['social_metas'])) {
            $socialMetas = [];
            foreach ($simplifiedEntry['seo']['social_metas'] as $socialMeta) {
                $contentParsed = [];
                foreach ($socialMeta['content'] as $key => $value) {
                    $contentParsed[] = [
                        "name" => $key,
                        "content" => $value
                    ];
                }
                $socialMetas[] = [
                    'handle' => $socialMeta['handle'],
                    'content' => $contentParsed
                ];
            }
            $simplifiedEntry['seo']['social_metas'] = $socialMetas;
        }

        return $simplifiedEntry;
    }
}