<?php

namespace SailCMS\Models;

use Exception;
use JsonException;
use League\Flysystem\FilesystemException;
use MongoDB\BSON\ObjectId;
use SailCMS\Cache;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\Http\Request;
use SailCMS\Models\Entry\Field as ModelField;
use SailCMS\Sail;
use SailCMS\Text;
use SailCMS\Types\Authors;
use SailCMS\Types\Dates;
use SailCMS\Types\EntryParent;
use SailCMS\Types\EntryStatus;
use SailCMS\Types\Fields\Field as InputField;
use SailCMS\Types\Listing;
use SailCMS\Types\LocaleField;
use SailCMS\Types\Pagination;
use SailCMS\Types\QueryOptions;
use SodiumException;
use stdClass;

class Entry extends Model
{
    // TODO Add caching for queries with filters

    /* Homepage config */
    const HOMEPAGE_CONFIG_HANDLE = 'homepage';
    const HOMEPAGE_CONFIG_ENTRY_TYPE_KEY = 'entry_type_handle';
    const HOMEPAGE_CONFIG_ENTRY_KEY = 'entry_id';

    /* Errors */
    const TITLE_MISSING = '5001: You must set the entry title in your data.';
    const STATUS_CANNOT_BE_TRASH = '5002: You cannot delete a entry this way, use the delete method instead.';
    const CANNOT_VALIDATE_CONTENT = '5003: You cannot validate content without setting an entry layout to the type.';
    const SCHEMA_VALIDATION_ERROR = '5004: The entry layout schema does not fits with the contents sends.';
    const CONTENT_KEY_ERROR = '5005: The key "%s" does not exists in the schema of the entry layout.';
    const CONTENT_ERROR = '5006: The content has theses errors :' . PHP_EOL;
    const DOES_NOT_EXISTS = '5007: Entry "%s" does not exists.';
    const DATABASE_ERROR = '5008: Exception when "%s" an entry.';
    const INVALID_FILTER_VALUE = '5009: Invalid filter value.';
    const INVALID_FILTER_TYPE = '5010: Invalid filter type (array is not allowed).';

    /* Cache */
    const HOMEPAGE_CACHE = 'homepage_entry';
    const FIND_BY_URL_CACHE = 'find_by_url_entry_'; // Add url at the end
    const ONE_CACHE_BY_ID = 'entry_'; // Add id at the end
    const ENTRY_BY_HANDLE_ALL = 'all_entry_'; // Add handle at the end

    /* Fields */
    public string $entry_type_id;
    public ?EntryParent $parent;
    public ?string $site_id;
    public string $locale;
    public Collection $alternates; // Array of object "locale" -> "lang_code", "entry" -> "entry_id"
    public string $status;
    public string $title;
    public ?string $slug;
    public string $url; // Concatenation of the slug and the entry type url_prefix
    public Authors $authors;
    public Dates $dates;
    public Collection $categories;
    public Collection $content;

    private EntryType $entryType;
    private EntryLayout $entryLayout;

    /**
     *
     *  Get the model according to the collection
     *
     * @param string $collection
     * @param EntryType|null $entryType
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function __construct(string $collection = '', EntryType $entryType = null)
    {
        if (!$entryType) {
            // Get or create the default entry type
            if (!$collection) {
                $this->entryType = EntryType::getDefaultType(false, true);
            } else {
                // Get entry type by collection name
                $this->entryType = EntryType::getByCollectionName($collection);
            }
        } else {
            $this->entryType = $entryType;
        }

        $this->entry_type_id = (string)$this->entryType->_id;
        $collection = $this->entryType->collection_name;

        parent::__construct($collection);
    }

    /**
     *
     * Initialize the entry
     *
     * @return void
     *
     */
    public function init(): void
    {
        $this->setPermissionGroup($this->entryType->handle);
    }

    /**
     *
     * Fields for entry
     *
     * @param bool $fetchAllFields
     * @return string[]
     *
     */
    public function fields(bool $fetchAllFields = false): array
    {
        return [
            '_id',
            'entry_type_id',
            'parent',
            'site_id',
            'locale',
            'alternates',
            'status',
            'title',
            'slug',
            'url',
            'authors',
            'dates',
            'categories',
            'content'
        ];
    }

    /**
     *
     * Get entry layout if entry type has one
     *
     * @return EntryLayout|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function getEntryLayout(): ?EntryLayout
    {
        if (!isset($this->entryLayout)) {
            if (!$this->entryType->entry_layout_id) {
                return null;
            }

            $this->entryLayout = (new EntryLayout())->one([
                '_id' => $this->entryType->entry_layout_id
            ]);
        }
        return $this->entryLayout;
    }

    /**
     *
     * Parse content
     *
     * @return Collection
     *
     */
    public function getContent(): Collection
    {
        $parsedContent = Collection::init();

        $this->content->each(function ($key, $modelFieldContent) use (&$parsedContent) {
            $parsedContent->push([
                'key' => $key,
                'type' => $modelFieldContent->type,
                'handle' => $modelFieldContent->handle,
                'content' => $modelFieldContent->content
            ]);
        });

        return $parsedContent;
    }

    /**
     *
     * Parse the entry into an array for api
     *
     * @param object|null $currentHomepageEntry
     * @param bool $wantSchema
     * @return array
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function toGraphQL(object|null $currentHomepageEntry, bool $wantSchema = true): array
    {
        $schema = Collection::init();
        if ($wantSchema) {
            $entryLayout = $this->getEntryLayout();

            if ($entryLayout) {
                $schema = $entryLayout->processSchemaToGraphQL();
            }
        }

        return [
            '_id' => $this->_id,
            'entry_type_id' => $this->entry_type_id,
            'is_homepage' => isset($currentHomepageEntry) && $this->_id == $currentHomepageEntry->{self::HOMEPAGE_CONFIG_ENTRY_KEY},
            'parent' => $this->parent ? $this->parent->toDBObject() : EntryParent::init(),
            'site_id' => $this->site_id,
            'locale' => $this->locale,
            'alternates' => $this->alternates,
            'status' => $this->status,
            'title' => $this->title,
            'slug' => $this->slug,
            'url' => $this->url,
            'authors' => $this->authors->toDBObject(),
            'dates' => $this->dates->toDBObject(),
            'categories' => $this->categories,
            'content' => $this->getContent(),
            'schema' => $schema
        ];
    }

    /**
     *
     * Get all entries by entry type handle
     *
     * @param string $entryTypeHandle
     * @param array|null $filters if filters is null it is default to ignore trash entries
     * @param int $page
     * @param int $limit
     * @param string $sort
     * @param int $direction
     * @return Listing
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     */
    public static function getList(string $entryTypeHandle, ?array $filters = null, int $page = 1, int $limit = 50, string $sort = 'title', int $direction = Model::SORT_ASC): Listing
    {
        $entryModel = EntryType::getEntryModelByHandle($entryTypeHandle);

        $offset = $page * $limit - $limit;

        if ($filters === null) {
            $filters = [
                'status' => ['$ne' => EntryStatus::TRASH->value]
            ];
        }

        $options = QueryOptions::initWithPagination($offset, $limit);
        $options->sort = [$sort => $direction];

        $results = $entryModel->find($filters, $options)->exec();

        $count = $entryModel->count($filters);
        $total = (integer)ceil($count / $limit);

        $pagination = new Pagination($page, $total, $count);
        return new Listing($pagination, new Collection($results));
    }


    /**
     *
     * Get homepage configs
     *
     * @param string $siteId
     * @param string|null $locale
     * @return object|null
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws JsonException
     * @throws SodiumException
     */
    public static function getHomepage(string $siteId, ?string $locale = null): object|null
    {
        $configHandle = self::homepageConfigHandle($siteId);

        $homepageConfig = Config::getByName($configHandle);

        if (!$homepageConfig) {
            return new stdClass();
        } else if ($locale) {
            return $homepageConfig->config->{$locale} ?? null;
        }
        return $homepageConfig->config;
    }

    /**
     *
     *
     *
     * @param string $siteId
     * @param string $locale
     * @param bool $toGraphQL
     * @return array|Entry|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SodiumException
     *
     */
    public static function getHomepageEntry(string $siteId, string $locale, bool $toGraphQL = false): array|Entry|null
    {
        $currentHomepageEntry = self::getHomepage($siteId, $locale);
        if (!$currentHomepageEntry) {
            return null;
        }

        $entryModel = EntryType::getEntryModelByHandle($currentHomepageEntry->{self::HOMEPAGE_CONFIG_ENTRY_TYPE_KEY});

        $cacheHandle = self::HOMEPAGE_CACHE . "_" . $siteId . "_" . $locale;
        $cacheTtl = $_ENV['SETTINGS']->get('entry.cacheTtl', Cache::TTL_WEEK);
        $entry = $entryModel->findById($currentHomepageEntry->{self::HOMEPAGE_CONFIG_ENTRY_KEY})->exec($cacheHandle, $cacheTtl);

        if ($toGraphQL) {
            return $entry->toGraphQL($currentHomepageEntry);
        }

        return $entry;
    }

    /**
     *
     * Find a content by the url
     *
     * @param string $url
     * @param bool $fromRequest
     * @return Entry|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public static function findByURL(string $url, bool $fromRequest = true): ?Entry
    {
        // Load all entry types before scanning them
        $availableTypes = EntryType::getAll();
        $request = $fromRequest ? new Request() : null;
        $content = null;

        $availableTypes->each(function ($key, $value) use ($url, $request, &$content) {
            // We already have it, stop!
            if ($content !== null) {
                return;
            }

            // Search for what collection has this url (if any)
            $entry = new Entry($value->collection_name);
            $found = $entry->count(['url' => $url, 'site_id' => Sail::siteId()]);

            if ($found > 0) {
                // Winner Winner Chicken Diner!
                $cache_ttl = $_ENV['SETTINGS']->get('entry.cacheTtl', Cache::TTL_WEEK);
                $content = $entry->findOne(['url' => $url, 'site_id' => Sail::siteId()])->exec(self::FIND_BY_URL_CACHE . $url, $cache_ttl);

                $preview = false;
                $previewVersion = false;
                if ($request) {
                    $preview = $request->get('pm', false, null);
                    $previewVersion = $request->get('pv', false, null);
                }

                // URL does not exist :/
                if (!$content) {
                    $content = null;
                }

                if (EntryStatus::from($content->status) !== EntryStatus::LIVE) {
                    // Page is not published but preview mode is active
                    if ($preview && $previewVersion) {
                        // TODO: HANDLE PREVIEW
                        //$content = null;

                    } else {
                        // Page exists but is not published
                        $content = null;
                    }
                }
            }
        });

        return $content;
    }

    /**
     *
     * Find entries of all types by category id
     *
     * @param string $categoryId
     * @param string|null $siteId
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public static function findByCategoryId(string $categoryId, string $siteId = null): Collection
    {
        $availableTypes = EntryType::getAll();
        $allEntries = Collection::init();
        $siteId = $siteId ?? Sail::siteId();

        $availableTypes->each(function ($key, $entryType) use ($categoryId, $siteId, &$allEntries) {
            $entry = new Entry($entryType->collection_name);
            $entries = $entry->find([
                'categories' => ['$in' => ['_id', $categoryId]],
                'site_id' => $siteId
            ])->exec();

            $allEntries->pushSpread(...$entries);
        });

        return $allEntries;
    }

    /**
     *
     * Get a validated slug that is not already existing in the db
     *
     * @param LocaleField $urlPrefix
     * @param string $slug
     * @param string $siteId
     * @param string $locale
     * @param string|null $currentId
     * @param Collection|null $availableTypes
     * @return string
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public static function getValidatedSlug(LocaleField $urlPrefix, string $slug, string $siteId, string $locale, ?string $currentId = null, Collection $availableTypes = null): string
    {
        // Just to be sure that the slug is ok
        $slug = Text::slugify($slug, $locale);

        // Form the url to find if it already exists
        $url = self::getRelativeUrl($urlPrefix, $slug, $locale);
        $found = 0;

        // Set the filters for the query
        $filters = ['url' => $url, 'site_id' => $siteId];
        if ($currentId) {
            $filters['_id'] = ['$ne' => new ObjectId($currentId)];
        }

        // Query only the first call
        if (!$availableTypes) {
            $availableTypes = EntryType::getAll();
        }
        $availableTypes->each(function ($key, $value) use ($filters, &$found) {
            // We already find one no need to continue the search
            if ($found > 0) {
                return;
            }
            $entry = new Entry($value->collection_name);
            $found = $entry->count($filters);
        });

        if ($found > 0) {
            $slug = self::incrementSlug($slug);
            return self::getValidatedSlug($urlPrefix, $slug, $siteId, $locale, $currentId, $availableTypes);
        }
        return $slug;
    }

    /**
     *
     * Get the relative url of the entry
     *
     * @param LocaleField $urlPrefix
     * @param string $slug
     * @param string $locale
     * @return string
     *
     */
    public static function getRelativeUrl(LocaleField $urlPrefix, string $slug, string $locale): string
    {
        $relativeUrl = "";

        $urlPrefixWithLocale = $urlPrefix->{$locale} ?? '';

        if ($urlPrefixWithLocale) {
            $relativeUrl .= $urlPrefixWithLocale . '/';
        }
        $relativeUrl .= $slug;

        return $relativeUrl;

    }

    /**
     *
     * Increment the slug when it exists in the db
     *
     * @param $slug
     * @return string
     *
     */
    public static function incrementSlug($slug): string
    {
        preg_match("/(?<base>[\w\d-]+-)(?<increment>\d+)$/", $slug, $matches);

        if (count($matches) > 0) {
            $increment = (int)$matches['increment'];
            $newSlug = $matches['base'] . ($increment + 1);
        } else {

            $newSlug = $slug . "-2";
        }

        return $newSlug;
    }

    /**
     *
     * Process content from graphQL to be able to create/update
     *
     * @param Collection|null $content
     * @return Collection
     *
     */
    public static function processContentFromGraphQL(?Collection $content): Collection
    {
        $parsedContent = Collection::init();

        $content?->each(function ($i, $toParse) use (&$parsedContent) {
            $content = $toParse->content;
            if ($toParse->content instanceof Collection) {
                $content = $toParse->content->unwrap();
            }

            $parsedContent->pushKeyValue($toParse->key, $content);
        });

        return $parsedContent;
    }

    /**
     *
     * Process errors for GraphQL
     *
     * @param Collection $errors
     * @return Collection
     *
     */
    public static function processErrorsForGraphQL(Collection $errors): Collection
    {
        $parsedErrors = Collection::init();
        $errors->each(function ($key, $errors) use (&$parsedErrors) {
            $parsedErrors->push([
                'key' => $key,
                'errors' => $errors
            ]);
        });

        return $parsedErrors;
    }

    /**
     *
     * Get an entry with filters
     *
     * @param array $filters
     * @return Entry|null
     * @throws DatabaseException
     *
     */
    public function one(array $filters): Entry|null
    {
        if (isset($filters['_id'])) {
            $cache_ttl = $_ENV['SETTINGS']->get('entry.cacheTtl', Cache::TTL_WEEK);
            return $this->findById($filters['_id'])->exec(self::ONE_CACHE_BY_ID . $filters['_id'], $cache_ttl);
        }

        return $this->findOne($filters)->exec();
    }

    /**
     *
     * Get the count according to given filters
     *
     * @param array $filters
     * @return int
     *
     */
    public function getCount(array $filters): int
    {
        return $this->count($filters);
    }

    /**
     *
     * Get all entries of the current type
     *  with filtering and pagination
     *
     * @param bool $ignoreTrash
     * @param ?array $filters
     * @return Collection
     * @throws DatabaseException
     *
     */
    public function all(bool $ignoreTrash = true, ?array $filters = []): Collection
    {
        // Caching when there is no filter
        $cache_key = null;
        $cache_ttl = Cache::TTL_WEEK;
        if (count($filters) === 0) {
            $cache_key = self::ENTRY_BY_HANDLE_ALL . $this->entryType->handle;
            $cache_ttl = $_ENV['SETTINGS']->get('entry.cacheTtl', Cache::TTL_WEEK);
        }

        if (!$ignoreTrash && !in_array('status', $filters)) {
            $filters['status'] = ['$ne' => EntryStatus::TRASH->value];
        }

        $result = $this->find($filters)->exec($cache_key, $cache_ttl);
        return new Collection($result);
    }

    /**
     *
     * Count entries for the current entry type
     *  (according to the __construct method)
     *
     * @param EntryStatus|string|null $status
     * @return int
     *
     */
    public function countEntries(EntryStatus|string|null $status = null): int
    {
        $filters = [];
        if ($status) {
            if ($status instanceof EntryStatus) {
                $status = $status->value;
            }

            $filters = ['status' => $status];
        }

        return $this->count($filters);
    }

    /**
     *
     * Create an entry
     *  The extra data can contains:
     *      - parent default null
     *      - authors default User::currentUser
     *      - categories default empty Collection
     *      - content default empty Collection
     *
     * @param bool $isHomepage
     * @param string $locale
     * @param EntryStatus|string $status
     * @param string $title
     * @param string|null $slug
     * @param array|Collection $extraData
     * @param bool $throwErrors
     * @return array|Entry|Collection|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SodiumException
     *
     */
    public function create(bool $isHomepage, string $locale, EntryStatus|string $status, string $title, ?string $slug = null, array|Collection $extraData = [], bool $throwErrors = true): array|Entry|Collection|null
    {
        $this->hasPermissions();

        if ($status instanceof EntryStatus) {
            $status = $status->value;
        }

        $data = new Collection([
            'locale' => $locale,
            'title' => $title,
            'status' => $status,
            'slug' => $slug
        ]);

        // Add the optional data to the creation
        if ($extraData) {
            $data->pushSpreadKeyValue(...$extraData);
        }

        $siteId = $data->get('site_id', Sail::siteId());

        $entryOrErrors = $this->createWithoutPermission($data, $throwErrors);

        if ($isHomepage && $entryOrErrors instanceof Entry) {
            $entryOrErrors->setAsHomepage($siteId, $locale);
        }

        return $entryOrErrors;
    }

    /**
     *
     * Update an entry with a given entry id or entry instance
     *
     * @param Entry|string $entry or id
     * @param array|Collection $data
     * @param bool $throwErrors
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException                $errors->pushKeyValue($key, [Field::]);
     * @throws SodiumException
     *
     */
    public function updateById(Entry|string $entry, array|Collection $data, bool $throwErrors = true): Collection
    {
        $this->hasPermissions();

        $entryId = $entry->_id ?? '';
        if (!$entry instanceof Entry) {
            $entryId = (string)$entry;
            $entry = $this->findById($entryId)->exec();
        }
        if (is_array($data)) {
            $data = new Collection($data);
        }

        if (!$entry) {
            throw new EntryException(sprintf(Entry::DOES_NOT_EXISTS, 'id = ' . $entryId));
        }

        $updateErrors = $this->updateWithoutPermission($entry, $data, $throwErrors);

        if ($updateErrors->length <= 0) {
            $this->handleHomepageUpdate($entry, $data);
        }

        return $updateErrors;
    }

    /**
     *
     * Update entries url according to an url prefix (normally comes from entry type)
     *
     * @param LocaleField $urlPrefix
     * @return void
     * @throws DatabaseException
     *
     */
    public function updateEntriesUrl(LocaleField $urlPrefix): void
    {
        $entries = $this->all();

        $writes = [];
        foreach ($entries as $entry) {
            $writes[] = [
                'updateOne' => [
                    ['_id' => $entry->_id],
                    ['$set' => ['url' => Entry::getRelativeUrl($urlPrefix, $entry->slug, $entry->locale)]]
                ]
            ];
        }

        if (count($writes)) {
            // Bulk write everything, performance++
            $this->bulkWrite($writes);
        }
    }

    /**
     *
     * Update all content keys with a new given key
     *
     * @param string $key
     * @param string $newKey
     * @return true
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    public function updateAllContentKey(string $key, string $newKey): bool
    {
        $entries = $this->all();

        $updates = [];
        $entries->each(function ($k, $entry) use (&$updates, $key, $newKey) {
            $newContent = Collection::init();
            $entry->content->each(function ($currentKey, $content) use (&$newContent, $key, $newKey) {
                if ($currentKey == $key) {
                    $currentKey = $newKey;
                }
                $newContent->pushKeyValue($currentKey, $content);
            });

            $updates[] = [
                'updateOne' => [
                    ['_id' => $entry->_id],
                    ['$set' => ['content' => $newContent->unwrap()]]
                ]
            ];
        });

        if (count($updates)) {
            try {
                $this->bulkWrite($updates);
            } catch (Exception $exception) {
                throw new EntryException(sprintf(self::DATABASE_ERROR, "bulk update content") . PHP_EOL . $exception->getMessage());
            }
        }
        return true;
    }

    /**
     *
     * Delete an entry in soft mode or definitively
     *
     * @param string|ObjectId $entryId
     * @param bool $soft
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SodiumException
     */
    public function delete(string|ObjectId $entryId, bool $soft = true): bool
    {
        $this->hasPermissions();

        $entry = $this->findById($entryId)->exec();
        if (!$entry) {
            throw new EntryException(sprintf(self::DOES_NOT_EXISTS, $entryId));
        }

        if ($soft) {
            $result = $this->softDelete($entry);
        } else {
            $result = $this->hardDelete($entryId);
        }

        // Update homepage if needed
        if ($result) {
            $currentHomepages = Entry::getHomepage($entry->site_id);
            $currentHomepage = $currentHomepages->{$entry->locale} ?? false;
            if ($currentHomepage && $currentHomepage->{self::HOMEPAGE_CONFIG_ENTRY_KEY} === (string)$entryId) {
                $this->emptyHomepage($entry->site_id, $entry->locale, $currentHomepages);
            }
        }

        return $result;
    }

    /**
     *
     * Process authors and dates fields
     *
     * @param string $field
     * @param mixed $value
     * @return mixed
     *
     */
    protected function processOnFetch(string $field, mixed $value): mixed
    {
        return match ($field) {
            "authors" => new Authors($value->created_by, $value->updated_by, $value->published_by, $value->deleted_by),
            "dates" => new Dates($value->created, $value->updated, $value->published, $value->deleted),
            "parent" => $value ? new EntryParent($value->handle, $value->parent_id) : null,
            "content" => $value instanceof stdClass ? new Collection((array)$value) : $value,
            default => $value,
        };
    }

    /**
     *
     * Validation on store
     *
     * @throws EntryException
     *
     */
    protected function processOnStore(string $field, mixed $value): mixed
    {
        // Data verification
        if ($field == "title" && empty($value)) {
            throw new EntryException(self::TITLE_MISSING);
        }

        return parent::processOnStore($field, $value);
    }

    /**
     *
     * Validate that status is not thrash
     *  because the only to set it to trash is in the delete method
     *
     * @param EntryStatus|string $status
     * @return void
     * @throws EntryException
     *
     */
    private static function validateStatus(EntryStatus|string $status): void
    {
        if ($status instanceof EntryStatus) {
            $status = $status->value;
        }
        if ($status === EntryStatus::TRASH->value) {
            throw new EntryException(self::STATUS_CANNOT_BE_TRASH);
        }
    }

    /**
     *
     * Validate content from the entry type layout schema
     *
     * @param Collection $content
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     */
    private function validateContent(Collection $content): Collection
    {
        $errors = Collection::init();

        $schema = null;
        if ($this->entryType->entry_layout_id) {
            $entryLayoutModel = new EntryLayout();
            $entryLayout = $entryLayoutModel->one(['_id' => $this->entryType->entry_layout_id]);
            $schema = $entryLayout->schema;
        }

        if ($content->length > 0 && !$schema) {
            throw new EntryException(self::CANNOT_VALIDATE_CONTENT);
        } else if (!$schema) {
            $schema = Collection::init();
        }

        // Validate content from schema
        $schema->each(function ($key, $modelField) use ($content, $errors) {
            /**
             * @var ModelField $modelField
             */
            $modelFieldContent = $content->get($key);

            // Cannot find content, it's not filled at all
            if ($modelFieldContent === null && $modelField->isRequired()) {
                $errors->pushKeyValue($key, [[InputField::FIELD_REQUIRED]]);
                return;
            } else if ($modelFieldContent === null) {
                return;
            }

            $modelFieldErrors = $modelField->validateContent($modelFieldContent);

            if ($modelFieldErrors->length > 0) {
                $errors->pushKeyValue($key, $modelFieldErrors->unwrap());
            }
        });

        $content->each(function ($key, $content) use ($schema) {
            if (!$schema->get($key)) {
                throw new EntryException(sprintf(self::CONTENT_KEY_ERROR, $key));
            }
        });

        return $errors;
    }

    /**
     *
     * Handle homepage config after update
     *
     * @param Entry $oldEntry
     * @param Collection $newData
     * @return void
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws JsonException
     * @throws SodiumException
     *
     */
    private function handleHomepageUpdate(Entry $oldEntry, Collection $newData): void
    {
        $newSiteId = $newData->get('site_id');
        $newLocale = $newData->get('locale');
        $homepageChange = $newData->get('is_homepage');

        $currentSiteId = $newSiteId ?? $oldEntry->site_id;
        $currentLocale = $newLocale ?? $oldEntry->locale;

        // According to the changes, update and/or remove entry from homepage.
        if (($newSiteId && $newSiteId != $currentSiteId) || ($newLocale && $newLocale != $currentLocale) || $homepageChange === true) {
            // Remove homepage
            self::emptyHomepage($oldEntry->site_id, $oldEntry->locale);
            // Add homepage
            $oldEntry->setAsHomepage($currentSiteId, $currentLocale);
        } else if ($homepageChange === false) {
            // Remove homepage
            self::emptyHomepage($oldEntry->site_id, $oldEntry->locale);
        }
    }

    /**
     *
     * Set the current entry has homepage
     *
     * @param string $siteId
     * @param string $locale
     * @param object|null $currentConfig
     * @return void
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws JsonException
     * @throws SodiumException
     */
    private function setAsHomepage(string $siteId, string $locale, object $currentConfig = null): void
    {
        if (!isset($currentConfig)) {
            $currentConfig = self::getHomepage($siteId);
        }

        $currentConfig->{$locale} = (object)[
            self::HOMEPAGE_CONFIG_ENTRY_KEY => (string)$this->_id,
            self::HOMEPAGE_CONFIG_ENTRY_TYPE_KEY => $this->entryType->handle
        ];

        Config::setByName(self::homepageConfigHandle($siteId), $currentConfig);
    }

    /**
     *
     * Empty the homepage for the current site
     *
     * @param string $siteId
     * @param string $locale
     * @param object|array|null $currentConfig
     * @return void
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws JsonException
     * @throws SodiumException
     */
    private function emptyHomepage(string $siteId, string $locale, object|array $currentConfig = null): void
    {
        if (!$currentConfig) {
            $currentConfig = self::getHomepage($siteId);
        }

        $currentConfig->{$locale} = null;
        Config::setByName(self::homepageConfigHandle($siteId), $currentConfig);
    }

    /**
     *
     * Create an entry
     *
     * @param Collection $data
     * @param bool $throwErrors
     * @return array|Entry|Collection|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    private function createWithoutPermission(Collection $data, bool $throwErrors = true): array|Entry|Collection|null
    {
        $locale = $data->get('locale');
        $status = $data->get('status', EntryStatus::INACTIVE->value);
        $title = $data->get('title');
        $slug = $data->get('slug', Text::slugify($title, $locale));
        $site_id = $data->get('site_id', Sail::siteId());
        $author = User::$currentUser;
        $alternates = new Collection($data->get('alternates', []));
        $parent = $data->get('parent');
        $content = $data->get('content');
        $categories = $data->get('categories');

        // VALIDATION & PARSING
        self::validateStatus($status);
        if ($content instanceof Collection && $content->length > 0) {
            // Check if there is errors
            $errors = $this->validateContent($content);

            if ($errors->length > 0) {
                if ($throwErrors) {
                    self::throwErrorContent($errors);
                } else {
                    return $errors;
                }
            }

            $content = $content->unwrap();
        }

        // Get the validated slug
        $slug = self::getValidatedSlug($this->entryType->url_prefix, $slug, $site_id, $locale);

        $published = false;
        if ($status == EntryStatus::LIVE->value) {
            $published = true;
        }

        $dates = Dates::init($published);
        $authors = Authors::init($author, $published);

        try {
            $entryId = $this->insert([
                'entry_type_id' => (string)$this->entryType->_id,
                'parent' => $parent,
                'site_id' => $site_id,
                'locale' => $locale,
                'alternates' => $alternates,
                'status' => $status,
                'title' => $title,
                'slug' => $slug,
                'url' => self::getRelativeUrl($this->entryType->url_prefix, $slug, $locale),
                'authors' => $authors,
                'dates' => $dates,
                'content' => $content ?? [],
                'categories' => $categories ?? []
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'creating') . PHP_EOL . $exception->getMessage());
        }

        $entry = $this->findById($entryId)->exec();
        // The query has the good entry type
        $entry->entryType = $this->entryType;

        return $entry;
    }

    /**
     *
     * Update an entry without permission protection
     *
     * @param Entry $entry
     * @param Collection $data
     * @param bool $throwErrors
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    private function updateWithoutPermission(Entry $entry, Collection $data, bool $throwErrors = true): Collection
    {
        $update = [];
        $slug = $entry->slug;
        $locale = $entry->locale;
        $site_id = $entry->site_id;

        if (in_array('locale', $data->keys()->unwrap())) {
            $locale = $data->get('locale');
        }
        if (in_array('site_id', $data->keys()->unwrap())) {
            $site_id = $data->get('site_id');
        }
        if (in_array('slug', $data->keys()->unwrap())) {
            $slug = $data->get('slug');
            $update['slug'] = self::getValidatedSlug($this->entryType->url_prefix, $slug, $site_id, $locale, $entry->_id);
        }
        if (in_array('status', $data->keys()->unwrap())) {
            self::validateStatus($data->get('status'));
        }

        if (in_array('content', $data->keys()->unwrap()) && $data->get('content')) {
            $errors = $this->validateContent($data->get('content'));

            if ($errors->length > 0) {
                if ($throwErrors) {
                    self::throwErrorContent($errors);
                } else {
                    return $errors;
                }
            }
        }

        $data->each(function ($key, $value) use (&$update) {
            if (in_array($key, ['parent', 'site_id', 'locale', 'status', 'title', 'categories', 'content', 'alternates'])) {
                $update[$key] = $value;
            }
        });

        // Automatic attributes
        $update['url'] = self::getRelativeUrl($this->entryType->url_prefix, $slug, $locale);
        $update['authors'] = Authors::updated($entry->authors, User::$currentUser->_id);
        $update['dates'] = Dates::updated($entry->dates);

        try {
            $this->updateOne(['_id' => $entry->_id], [
                '$set' => $update
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'updating') . PHP_EOL . $exception->getMessage());
        }

        return Collection::init();
    }

    /**
     *
     * Put an entry in the trash
     *
     * @param Entry $entry
     * @return bool
     * @throws EntryException
     *
     */
    private function softDelete(Entry $entry): bool
    {
        $authors = Authors::deleted($entry->authors, User::$currentUser->_id);
        $dates = Dates::deleted($entry->dates);

        try {
            $qtyUpdated = $this->updateOne(['_id' => $entry->_id], [
                '$set' => [
                    'authors' => $authors,
                    'dates' => $dates,
                    'status' => EntryStatus::TRASH->value
                ]
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'soft deleting') . PHP_EOL . $exception->getMessage());
        }

        return $qtyUpdated === 1;
    }

    /**
     *
     * Delete an entry definitively
     *
     * @throws EntryException
     *
     */
    private function hardDelete(string|ObjectId $entryTypeId): bool
    {
        try {
            $qtyDeleted = $this->deleteById((string)$entryTypeId);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'hard deleting') . PHP_EOL . $exception->getMessage());
        }

        return $qtyDeleted === 1;
    }

    /**
     *
     * Parse and throw the content errors
     *
     * @param Collection $errors
     * @return void
     * @throws EntryException
     *
     */
    private static function throwErrorContent(Collection $errors): void
    {
        $errorsStrings = [];

        $errors->each(function ($key, $errorsArray) use (&$errorsStrings) {
            foreach ($errorsArray as $error) {
                $errorsStrings[] = rtrim($error[0], '.') . ": " . $key;
            }
        });

        if (count($errorsStrings) > 0) {
            throw new EntryException(self::CONTENT_ERROR . implode('\t,' . PHP_EOL, $errorsStrings));
        }
    }

    /**
     *
     * Homepage config handle for a given site id
     *
     * @param $siteId
     * @return string
     */
    private static function homepageConfigHandle($siteId): string
    {
        return self::HOMEPAGE_CONFIG_HANDLE . "_" . $siteId;
    }
}
