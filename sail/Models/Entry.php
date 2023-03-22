<?php

namespace SailCMS\Models;

use Exception;
use JsonException;
use League\Flysystem\FilesystemException;
use MongoDB\BSON\ObjectId;
use SailCMS\Cache;
use SailCMS\Collection;
use SailCMS\Contracts\Validator;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\Event;
use SailCMS\Http\Request;
use SailCMS\Locale;
use SailCMS\Log as SailLog;
use SailCMS\Middleware;
use SailCMS\Middleware\Data;
use SailCMS\Middleware\Entry as MEntry;
use SailCMS\Models\Entry\Field;
use SailCMS\Models\Entry\Field as ModelField;
use SailCMS\Sail;
use SailCMS\Search as SailSearch;
use SailCMS\Text;
use SailCMS\Types\Authors;
use SailCMS\Types\Dates;
use SailCMS\Types\EntryParent;
use SailCMS\Types\Fields\Field as InputField;
use SailCMS\Types\Listing;
use SailCMS\Types\LocaleField;
use SailCMS\Types\MiddlewareType;
use SailCMS\Types\Pagination;
use SailCMS\Types\PublicationDates;
use SailCMS\Types\PublicationStatus;
use SailCMS\Types\QueryOptions;
use SailCMS\Types\StoringType;
use SodiumException;
use stdClass;

/**
 *
 * @property string $entry_type_id
 * @property ?EntryParent $parent
 * @property ?string $site_id
 * @property string $locale
 * @property Collection $alternates
 * @property bool $trashed = false
 * @property string $title
 * @property string $template
 * @property ?string $slug
 * @property string $url
 * @property Authors $authors
 * @property Dates $dates
 * @property Collection $categories
 * @property Collection $content
 *
 */
class Entry extends Model implements Validator
{
    protected string $collection = '';
    protected array $casting = [
        'parent' => EntryParent::class,
        'alternates' => Collection::class,
        'authors' => Authors::class,
        'dates' => Dates::class,
        'categories' => Collection::class,
        'content' => Collection::class
    ];

    protected array $validators = [
        'title' => self::class
    ];

    /* Homepage config */
    public const HOMEPAGE_CONFIG_HANDLE = 'homepage';
    public const HOMEPAGE_CONFIG_ENTRY_TYPE_KEY = 'entry_type_handle';
    public const HOMEPAGE_CONFIG_ENTRY_KEY = 'entry_id';

    /* Errors */
    public const TITLE_MISSING = '5001: You must set the entry title in your data.';
    public const CANNOT_VALIDATE_CONTENT = '5002: You cannot validate content without setting an entry layout to the type.';
    public const TEMPLATE_NOT_SET = '5003: Template property of the entry is not set.';
    public const CONTENT_KEY_ERROR = '5004: The key "%s" does not exist in the schema of the entry layout.';
    public const CONTENT_ERROR = '5005: The content has theses errors :' . PHP_EOL;
    public const DOES_NOT_EXISTS = '5006: Entry "%s" does not exist.';
    public const DATABASE_ERROR = '5007: Exception when "%s" an entry.';

    /* Cache */
    private const HOMEPAGE_CACHE = 'homepage_entry_';         // Add site id and locale at the end
    private const ONE_CACHE_BY_ID = 'entry_';                 // Add id at the end
    private const ENTRY_CACHE_BY_HANDLE_ALL = 'all_entry_';   // Add handle at the end
    private const ENTRY_FILTERED_CACHE = 'entries_filtered_'; // Add result of generateFilteredCacheKey
    private const ENTRY_CATEGORY_CACHE = 'entries_by_category_'; // Add category id

    public const EVENT_DELETE = 'event_delete_entry';
    public const EVENT_CREATE = 'event_create_entry';
    public const EVENT_UPDATE = 'event_update_entry';

    private EntryType $entryType;
    private EntryLayout $entryLayout;
    private EntrySeo $entrySeo;

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
        $this->collection = $this->entryType->collection_name;

        parent::__construct();
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
        $this->permissionGroup = $this->entryType->handle;
    }

    /**
     *
     * Validate fields
     *
     * @param string $key
     * @param mixed $value
     * @return void
     * @throws EntryException
     *
     */
    public static function validate(string $key, mixed $value): void
    {
        if ($key === 'title' && empty($value)) {
            throw new EntryException(self::TITLE_MISSING);
        }
    }

    /**
     *
     * Get basic entry document by its id
     *
     * @param string|ObjectId $id
     * @return Entry|null
     * @throws DatabaseException
     *
     */
    public function getById(string|ObjectId $id): ?Entry
    {
        return $this->findById($id)->exec('entry_' . (string)$id);
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
     * Get content with Model Field data
     *
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function getContent(): Collection
    {
        $parsedContent = Collection::init();

        $schema = $this->getSchema(true);

        $schema->each(function ($key, $modelField) use (&$parsedContent) {
            /**
             * @var Field $modelField
             */
            $content = $this->content->get($key);
            if ($modelField->storingType() === StoringType::ARRAY->value) {
                $content = json_encode($content ?? []);
            }

            $parsedContent->pushKeyValue($key, [
                'type' => $modelField->storingType(),
                'handle' => $modelField->handle,
                'content' => $content ?? '',
                'key' => $key
            ]);
        });

        return $parsedContent;
    }

    /**
     *
     * Get All SEO data for this entry
     *
     * @param bool $refresh
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function getSEO(bool $refresh = false): Collection
    {
        $seo = Collection::init();

        if (!isset($this->entrySeo) || $refresh) {
            $this->entrySeo = (new EntrySeo())->getOrCreateByEntryId($this->_id, $this->title);
        }

        $seo->pushKeyValue('locale', $this->locale);
        $seo->pushKeyValue('url', $this->url);
        $seo->pushKeyValue('alternates', $this->alternates);

        $seo->pushSpreadKeyValue(...$this->entrySeo->simplify(true));

        return $seo;
    }

    /**
     *
     * Return simplified SEO
     *
     * @return array
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function getSimplifiedSEO(): array
    {
        $seo = $this->getSEO();

        $socialMetas = $seo->get('social_metas');

        foreach ($socialMetas as &$socialMeta) {
            $newContent = [];
            foreach ($socialMeta['content'] as $key => $content) {
                $newContent[] = [
                    'name' => $key,
                    'content' => $content
                ];
            }
            $socialMeta['content'] = $newContent;
        }
        $seo->setFor('social_metas', $socialMetas);

        return $seo->unwrap();
    }

    /**
     *
     * Parse the entry into an array for api
     *
     * @param object|null $currentHomepageEntry
     * @return array
     *
     */
    public function simplify(object|null $currentHomepageEntry): array
    {
        return [
            '_id' => $this->_id,
            'entry_type_id' => $this->entry_type_id,
            'is_homepage' => isset($currentHomepageEntry) && (string)$this->_id === $currentHomepageEntry->{self::HOMEPAGE_CONFIG_ENTRY_KEY},
            'parent' => $this->parent ? $this->parent->castFrom() : EntryParent::init(),
            'site_id' => $this->site_id,
            'locale' => $this->locale,
            'alternates' => $this->alternates,
            'trashed' => $this->trashed ?? false,
            'title' => $this->title,
            'template' => $this->template ?? "", // Temporary because it's a new field
            'slug' => $this->slug,
            'url' => $this->url,
            'authors' => $this->authors->castFrom(),
            'dates' => $this->dates->castFrom(),
            'categories' => $this->categories->castFrom(),
            'current' => $this
        ];
    }

    /**
     *
     * Gather data for search purpose
     *
     * @return array
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function searchData(): array
    {
        $parsedContents = [
            'entry_type_handle' => $this->entryType->handle
        ];
        $schema = $this->getSchema(true);
        $this->content->each(function ($key, $content) use (&$parsedContents, $schema) {
            $parsedContent = $content;

            /**
             * @var Field $field
             */
            $field = $schema->get($key);
            $isSearchable = $field::SEARCHABLE && $field;

            if ($isSearchable) {
                if ($content instanceof stdClass) {
                    $parsedContent = implode('|', (array)$content);
                }
                $parsedContents[$key] = $parsedContent;
            }
        });


        return [
            '_id' => $this->_id,
            'title' => $this->title,
            'locale' => $this->locale,
            'content' => implode('|', $parsedContents),
        ];
    }

    /**
     *
     * Get all entries by entry type handle
     *
     * @param string $entryTypeHandle
     * @param string $search
     * @param int $page
     * @param int $limit
     * @param string $sort
     * @param int $direction
     * @param bool $ignoreTrash
     * @return Listing
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public static function getList(string $entryTypeHandle, string $search = '', int $page = 1, int $limit = 50, string $sort = 'title', int $direction = Model::SORT_ASC, bool $ignoreTrash = true): Listing
    {
        $entryModel = EntryType::getEntryModelByHandle($entryTypeHandle);

        $offset = $page * $limit - $limit;

        // Ignore trash entries
        $filters = [];
        if ($ignoreTrash) {
            $filters['trashed'] = ['$in' => [false, null]];
        }

        // Option for pagination
        $options = QueryOptions::initWithPagination($offset, $limit);
        $options->sort = [$sort => $direction];

        if ($search) {
            $searchResults = (new SailSearch())->search($search);
            $entryIds = [];
            $searchResults->results->each(function ($key, $searchResult) use (&$entryIds) {
                $entryIds[] = $searchResult->document_id;
            });

            $filters['_id'] = ['$in' => (new static())->ensureObjectIds($entryIds)->unwrap()];
        }

        // Actual query
        $cacheKey = self::generateCacheKeyFromFilters($entryTypeHandle, $filters) . '_' . $offset . '_' . $sort . '_' . $direction;
        $cacheTtl = setting('entry.cacheTtl', Cache::TTL_WEEK);
        $results = $entryModel->find($filters, $options)->exec($cacheKey, $cacheTtl);

        // Data for pagination
        $count = $entryModel->count($filters);
        $total = (integer)ceil($count / $limit);

        $pagination = new Pagination($page, $total, $count);
        return new Listing($pagination, new Collection($results));
    }

    /**
     *
     * Index all entries for search.
     *
     * @param string $entryTypeHandle
     * @return int
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public static function indexByEntryType(string $entryTypeHandle = EntryType::DEFAULT_HANDLE): int
    {
        $entryModel = EntryType::getEntryModelByHandle($entryTypeHandle);

        $entries = $entryModel->all(false);

        $entries->each(function ($key, $entry) {
            /**
             * @var Entry $entry
             */
            (new SailSearch())->store($entry->searchData(), $entry->_id);
        });

        return $entries->length;
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
     *
     */
    public static function getHomepage(string $siteId, ?string $locale = null): object|null
    {
        $configHandle = self::homepageConfigHandle($siteId);

        $homepageConfig = Config::getByName($configHandle);

        if (!$homepageConfig) {
            return new stdClass();
        }

        if ($locale) {
            return $homepageConfig->config->{$locale} ?? null;
        }

        return $homepageConfig->config;
    }

    /**
     *
     * Get homepage entry !
     *
     * @param string $siteId
     * @param string $locale
     * @param bool $simplify
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
    public static function getHomepageEntry(string $siteId, string $locale, bool $simplify = false): array|Entry|null
    {
        $currentHomepageEntry = self::getHomepage($siteId, $locale);
        if (!$currentHomepageEntry || !isset($currentHomepageEntry->{self::HOMEPAGE_CONFIG_ENTRY_TYPE_KEY})) {
            return null;
        }

        $entryModel = EntryType::getEntryModelByHandle($currentHomepageEntry->{self::HOMEPAGE_CONFIG_ENTRY_TYPE_KEY}, $simplify);

        $cacheHandle = self::HOMEPAGE_CACHE . $siteId . "_" . $locale;
        $cacheTtl = setting('entry.cacheTtl', Cache::TTL_WEEK);
        $entry = $entryModel->findById($currentHomepageEntry->{self::HOMEPAGE_CONFIG_ENTRY_KEY})->exec($cacheHandle, $cacheTtl);

        if ($simplify) {
            return $entry->simplify($currentHomepageEntry);
        }

        return $entry;
    }

    /**
     *
     * Find a content by the url
     *
     * @param string $url
     * @param string|null $siteId
     * @param bool $fromRequest
     * @param bool $preview
     * @param string $previewVersion
     * @return Entry|array|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public static function findByURL(string $url, ?string $siteId = null, bool $fromRequest = true, bool $preview = false, string $previewVersion = ""): Entry|array|null
    {
        $url = ltrim($url, "/");
        $request = $fromRequest ? new Request() : null;

        if ($request) {
            $preview = $request->get('pm', false, null);
            $previewVersion = $request->get('pv', false, null);
        }

        if ($preview || $previewVersion) {
            return self::findByUrlFromEntryTypes($url, $previewVersion);
        } else {
            return self::findByPublishedUrl($url, $siteId);
        }
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
            $cacheTtl = setting('entry.cacheTtl', Cache::TTL_WEEK);
            $entries = $entry->find([
                'categories' => ['$in' => ['_id', $categoryId]],
                'site_id' => $siteId
            ])->exec(self::ENTRY_CATEGORY_CACHE . $categoryId . "_" . $siteId . "_" . $entryType->_id, $cacheTtl);

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
            $content = json_decode($toParse->content);

            if (is_array($content) || $content instanceof stdClass) {
                $parsed = new Collection((array)$content);
            } else {
                $parsed = $toParse->content;
            }

            $parsedContent->pushKeyValue($toParse->key, $parsed);
        });

        return $parsedContent;
    }

    /**
     *
     * Combine and update content for GraphQL
     *
     * @param string $entryId
     * @param Collection $newContent
     * @return Collection
     * @throws DatabaseException
     *
     */
    public function updateContentForGraphQL(string $entryId, Collection $newContent): Collection
    {
        $entry = $this->findById($entryId)->exec();

        if (isset($entry->_id)) {
            $entry->content->each(function ($key, $content) use (&$newContent) {
                $currentNewContent = $newContent->get($key);

                if ($currentNewContent instanceof Collection) {
                    foreach ($content as $subKey => $subContent) {
                        $subNewContent = $currentNewContent->get($subKey);

                        if (!$subNewContent) {
                            $currentNewContent->pushKeyValue($subKey, $subContent);
                        }
                    }
                    $newContent->pushKeyValue($key, $currentNewContent);
                } else {
                    if (!$currentNewContent) {
                        $newContent->pushKeyValue($key, $content);
                    }
                }
            });
        }

        // Ensure that all content are arrays
        $newContent->each(function ($key, &$content) use (&$newContent) {
            if ($content instanceof stdClass) {
                $newContent->pushKeyValue($key, (array)$content);
            }
        });

        return $newContent;
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
     * @param bool $cache
     * @return Entry|null
     * @throws DatabaseException
     *
     */
    public function one(array $filters, bool $cache = true): Entry|null
    {
        $cacheTtl = $cache ? setting('entry.cacheTtl', Cache::TTL_WEEK) : 0;
        $cacheKey = $cache ? self::generateCacheKeyFromFilters($this->entryType->handle . "_one_", $filters) : '';
        $qs = $this->findOne($filters);
        if (isset($filters['_id'])) {
            $cacheKey = $cache ? self::ONE_CACHE_BY_ID . $filters['_id'] : '';
            $qs = $this->findById((string)$filters['_id']);
        }

        if ($cache) {
            return $qs->exec(/*$cacheKey, $cacheTtl*/);
        }
        return $qs->exec();
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
     * Get all entries of the current type without pagination
     *
     * @param bool $ignoreTrash
     * @param ?array $filters
     * @return Collection
     * @throws DatabaseException
     *
     */
    public function all(bool $ignoreTrash = true, ?array $filters = []): Collection
    {
        // Fast selection of only valid entry (not thrashed)
        if (!$ignoreTrash && !in_array('trashed', $filters)) {
            // Want everything but trash
            $filters['trashed'] = false;
        }

        // According to the filters, create the cache key
        if (count($filters) === 0) {
            $cacheKey = self::ENTRY_CACHE_BY_HANDLE_ALL . $this->entryType->handle;
        } else {
            $cacheKey = self::generateCacheKeyFromFilters($this->entryType->handle, $filters);
        }

        // Cache Time To Live value from setting or default
        $cacheTtl = setting('entry.cacheTtl', Cache::TTL_WEEK);

        // Actual query!!!
        $result = $this->find($filters)->exec($cacheKey, $cacheTtl);
        return new Collection($result);
    }

    /**
     *
     * Count entries for the current entry type
     *  (according to the __construct method)
     *
     * @param bool $ignoreTrash
     * @return int
     */
    public function countEntries(bool $ignoreTrash = false): int
    {
        $filters = [];
        if ($ignoreTrash) {
            $filters['trashed'] = false;
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
     * @param string $title
     * @param string $template
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
    public function create(bool $isHomepage, string $locale, string $title, string $template, ?string $slug = null, array|Collection $extraData = [], bool $throwErrors = true): array|Entry|Collection|null
    {
        $this->hasPermissions();

        $data = new Collection([
            'locale' => $locale,
            'title' => $title,
            'template' => $template,
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
     * @param bool $bypassContentValidation
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SodiumException
     *
     */
    public function updateById(Entry|string $entry, array|Collection $data, bool $throwErrors = true, bool $bypassContentValidation = false): Collection
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
            throw new EntryException(sprintf(self::DOES_NOT_EXISTS, $entryId));
        }

        $updateErrors = $this->updateWithoutPermission($entry, $data, $throwErrors, $bypassContentValidation);

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
     * @throws JsonException
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
     * Create an entry version then an entry publication
     *
     * @param string $entryId
     * @param int $publicationDate
     * @param int $expirationDate
     * @param string|null $siteId
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
    public function publish(string $entryId, int $publicationDate, int $expirationDate, string $siteId = null): string
    {
        $author = User::$currentUser;

        if (!$siteId) {
            $siteId = Sail::siteId();
        }

        $entryVersionModel = new EntryVersion();
        $lastVersion = $entryVersionModel->getLastVersionByEntryId($entryId);

        // It is almost impossible that an entry has no version, but just to be sure
        if (!$lastVersion) {
            $entry = $this->findById($entryId)->exec();
            $simplifiedEntry = $entry->simplify(null);
            $simplifiedEntry['content'] = $entry->content;
            $entryVersionID = (new EntryVersion)->create($author, $simplifiedEntry);
            $entryUrl = $simplifiedEntry['url'];
        }

        $entryVersionID = !isset($entryVersionID) ? $lastVersion->_id : $entryVersionID;
        $entryUrl = !isset($entryUrl) ? $lastVersion->entry->get('url') : $entryUrl;

        // Must override entryUrl if it's the homepage
        $currentHomepage = self::getHomepage($siteId, $lastVersion->entry->get('locale'));
        if ($currentHomepage->{self::HOMEPAGE_CONFIG_ENTRY_KEY} === $entryId) {
            $entryLocale = $lastVersion->entry->get('locale');
            $entryUrl = "";
            if ($entryLocale !== Locale::default()) {
                $entryUrl = $entryLocale . "/";
            }
        }

        return (new EntryPublication())->create($author, $entryId, $siteId, $entryUrl, (string)$entryVersionID, $publicationDate, $expirationDate);
    }

    /**
     *
     * Remove all entry publication to unpublish
     *
     * @param string $entryId
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function unpublish(string $entryId): bool
    {
        return (new EntryPublication())->deleteAllByEntryId($entryId);
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
     *
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
     * Get schema from entryLayout
     *
     * @param bool $silent
     * @param bool $simplified
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function getSchema(bool $silent = false, bool $simplified = false): Collection
    {
        $entryLayout = $this->getEntryLayout();

        // Handling error
        $errorMessage = sprintf(EntryLayout::DOES_NOT_EXISTS, $this->entryType->entry_layout_id);
        if ($this->entryType->handle === EntryType::DEFAULT_HANDLE) {
            $errorMessage .= PHP_EOL . "Check your configuration value for entry/defaultType/entryLayoutId.";
        }

        if (!$entryLayout && !$silent) {
            throw new EntryException($errorMessage);
        } else {
            if (!$entryLayout) {
                SailLog::logger()->warning($errorMessage);
            }
        }

        if ($simplified) {
            return $entryLayout ? $entryLayout->simplifySchema() : Collection::init();
        }

        $result = $entryLayout ? $entryLayout->schema : Collection::init();

        if (is_array($result)) {
            $result = new Collection($result);
        }

        return $result;
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
            $schema = $this->getSchema();
        }

        if ($content->length > 0 && !$schema) {
            throw new EntryException(self::CANNOT_VALIDATE_CONTENT);
        } else {
            if (!$schema) {
                $schema = Collection::init();
            }
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
            } else {
                if ($modelFieldContent === null) {
                    return;
                }
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
        } else {
            if ($homepageChange === false) {
                // Remove homepage
                self::emptyHomepage($oldEntry->site_id, $oldEntry->locale);
            }
        }
    }

    /**
     *
     * Set the current entry has homepage
     *
     * @param string $siteId
     * @param string $locale
     * @return void
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws JsonException
     * @throws SodiumException
     */
    private function setAsHomepage(string $siteId, string $locale): void
    {
        $currentConfig = self::getHomepage($siteId);
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
     *
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
        $title = $data->get('title');
        $template = $data->get('template');
        $slug = $data->get('slug', Text::slugify($title, $locale));
        $site_id = $data->get('site_id', Sail::siteId());
        $author = User::$currentUser;
        $alternates = new Collection($data->get('alternates', []));
        $parent = $data->get('parent');
        $content = $data->get('content');
        $categories = $data->get('categories');

        // VALIDATION & PARSING
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

        $dates = Dates::init();
        $authors = Authors::init($author);

        $data = [
            'entry_type_id' => (string)$this->entryType->_id,
            'parent' => $parent,
            'site_id' => $site_id,
            'locale' => $locale,
            'alternates' => $alternates,
            'title' => $title,
            'template' => $template,
            'slug' => $slug,
            'url' => self::getRelativeUrl($this->entryType->url_prefix, $slug, $locale),
            'authors' => $authors,
            'dates' => $dates,
            'content' => $content ?? [],
            'categories' => $categories ?? []
        ];

        // Middleware call
        $mResult = Middleware::execute(MiddlewareType::ENTRY, new Data(MEntry::BeforeCreate, data: $data));

        try {
            $entryId = $this->insert($mResult->data);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'creating') . PHP_EOL . $exception->getMessage());
        }

        $entry = $this->findById($entryId)->exec();
        // The query has the good entry type
        $entry->entryType = $this->entryType;

        // Version save with simplify entry
        $simplifiedEntry = $entry->simplify(null);
        $simplifiedEntry['content'] = $entry->content;
        (new EntryVersion)->create($author, $simplifiedEntry);

        // Search
        (new SailSearch())->store($entry->searchData(), $entry->_id);

        // Dispatch event
        Event::dispatch(self::EVENT_CREATE, [
            'entry' => $entry
        ]);

        return $entry;
    }

    /**
     *
     * Update an entry without permission protection
     *
     * @param Entry $entry
     * @param Collection $data
     * @param bool $throwErrors
     * @param bool $bypassContentValidation
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    private function updateWithoutPermission(Entry $entry, Collection $data, bool $throwErrors = true, bool $bypassContentValidation = false): Collection
    {
        $update = [];
        $author = User::$currentUser;
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

        // We bypass content validation when we apply a version
        if (!$bypassContentValidation && in_array('content', $data->keys()->unwrap()) && $data->get('content')) {
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
            if (in_array($key, ['parent', 'site_id', 'locale', 'title', 'template', 'categories', 'content', 'alternates'])) {

                $update[$key] = $value;
            }
        });

        // Automatic attributes
        $update['url'] = self::getRelativeUrl($this->entryType->url_prefix, $slug, $locale);
        $update['authors'] = Authors::updated($entry->authors, $author->_id);
        $update['dates'] = Dates::updated($entry->dates);

        // Middleware call
        $mResult = Middleware::execute(MiddlewareType::ENTRY, new Data(MEntry::BeforeUpdate, data: $update));

        try {
            $this->updateOne(['_id' => $entry->_id], [
                '$set' => $mResult->data
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'updating') . PHP_EOL . $exception->getMessage());
        }

        // Could we avoid to get the entry ?
        $entry = $this->findById($entry->_id)->exec();
        // The query has the good entry type
        $entry->entryType = $this->entryType;

        // Version save with simplified entry
        $simplifiedEntry = $entry->simplify(null);
        $simplifiedEntry['content'] = $entry->content;
        $versionId = (new EntryVersion)->create($author, $simplifiedEntry);

        // Update search
        (new SailSearch())->store($entry->searchData(), $entry->_id);

        // Dispatch event
        Event::dispatch(self::EVENT_UPDATE, [
            'entry' => $entry,
            'update' => $mResult->data,
            'versionId' => $versionId
        ]);

        // Return no errors
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
                    'trashed' => true
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
     * @param string|ObjectId $entryId
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    private function hardDelete(string|ObjectId $entryId): bool
    {
        try {
            $qtyDeleted = $this->deleteById((string)$entryId);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'hard deleting') . PHP_EOL . $exception->getMessage());
        }

        // Must delete seo data
        try {
            (new EntrySeo())->deleteByEntryId((string)$entryId, false);
        } catch (Exception $e) {
            // Do nothing because there is no entry seo for this entry
        }

        // And publications
        (new EntryPublication())->deleteAllByEntryId((string)$entryId);

        // And entry versions too
        (new EntryVersion())->deleteAllByEntryId((string)$entryId);

        // And search
        (new SailSearch())->remove($entryId);

        // Dispatch event
        Event::dispatch(self::EVENT_DELETE, [
            'entryId' => (string)$entryId,
        ]);

        return $qtyDeleted === 1;
    }

    /**
     *
     * Find entry content by entry publication
     *
     * @param string $url
     * @return Entry|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    private static function findByPublishedUrl(string $url, string $siteId = null): ?Entry
    {
        $content = null;

        if (!$siteId) {
            $siteId = Sail::siteId();
        }

        $publication = (new EntryPublication())->getPublicationByUrl($url, $siteId);

        if ($publication && PublicationDates::getStatus($publication->dates) === PublicationStatus::PUBLISHED->value) {
            $entryTypeId = $publication->version->entry->get('entry_type_id');
            $entryModel = (new EntryType())->getById($entryTypeId, false)->getEntryModel();
            $entry = $entryModel->one(['_id' => $publication->entry_id]);
            $content = (new EntryVersion())->fakeVersion($entry, $publication->entry_version_id);
        }

        return $content;
    }

    /**
     *
     * Find by url from entry types
     *
     * @param string $url
     * @param string|null $previewVersion
     * @return Entry|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     */
    private static function findByUrlFromEntryTypes(string $url, ?string $previewVersion): ?Entry
    {
        // Load all entry types before scanning them
        $availableTypes = EntryType::getAll();
        $content = null;

        $availableTypes->each(function ($key, $value) use ($url, $previewVersion, &$content) {
            // Search for what collection has this url (if any)
            $entry = new Entry($value->collection_name);
            $found = $entry->count(['url' => $url, 'site_id' => Sail::siteId()]);

            if ($found > 0) {
                // Winner Winner Chicken Diner!
                $content = $entry->one(['url' => $url, 'site_id' => Sail::siteId()]);

                // URL does not exist :/
                if (!$content) {
                    $content = null;
                }

                // Page is not published but preview mode is active
                if ($content && $previewVersion) {
                    $content = (new EntryVersion())->fakeVersion($content, $previewVersion);
                    return;
                }

                // Check if publication exists
                $publication = (new EntryPublication())->getPublicationByEntryId($content->_id);
                if ($publication && $publication->version->entry->get('url') === $url) {
                    $content = (new EntryVersion())->fakeVersion($content, $publication->entry_version_id);
                }

                if ($content) {
                    return;
                }
            }
        });

        return $content;
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
            foreach ($errorsArray as $fieldKey => $error) {
                if (is_array($error)) {
                    foreach ($error as $fieldError) {
                        $keyDisplay = $key;
                        if (!is_int($fieldKey)) {
                            $keyDisplay .= "." . $fieldKey;
                        }
                        $errorsStrings[] = rtrim($fieldError, '.') . " (" . $keyDisplay . ")";
                    }
                } else {
                    $errorsStrings[] = rtrim($error, '.') . "(" . $key . ")";
                }
            }
        });

        if (count($errorsStrings) > 0) {
            throw new EntryException(self::CONTENT_ERROR . implode("\t," . PHP_EOL, $errorsStrings));
        }
    }

    /**
     *
     * Homepage config handle for a given site id
     *
     * @param $siteId
     * @return string
     *
     */
    private static function homepageConfigHandle($siteId): string
    {
        return self::HOMEPAGE_CONFIG_HANDLE . "_" . $siteId;
    }

    /**
     *
     * Generate cache key from filters
     *
     * @param string $handle
     * @param Collection|array $filters
     * @return string
     *
     */
    private static function generateCacheKeyFromFilters(string $handle, Collection|array $filters): string
    {
        return self::ENTRY_FILTERED_CACHE . $handle . self::iterateIntoFilters($filters);
    }

    /**
     *
     * Iterate into filters recursively.
     *
     * @param mixed $iterableOrValue
     * @return string
     *
     */
    private static function iterateIntoFilters(mixed $iterableOrValue): string
    {
        $result = "";
        if (!is_array($iterableOrValue) && !$iterableOrValue instanceof Collection) {
            if ($iterableOrValue) {
                $result = "=" . str_replace(' ', '-', $iterableOrValue);
            } else {
                $result = "=null";
            }
        } else {
            foreach ($iterableOrValue as $key => $valueOrIterable) {
                $prefix = "+" . $key;
                if (!is_string($key)) {
                    $prefix = "";
                } else {
                    if (in_array($key, ['$or', '$and', '$nor'])) {
                        $prefix = "|" . $key;
                    } else {
                        if (str_starts_with($key, '$')) {
                            $prefix = ">" . $key;
                        }
                    }
                }

                $result .= $prefix . self::iterateIntoFilters($valueOrIterable);
            }
        }
        return $result;
    }
}
