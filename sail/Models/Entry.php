<?php

namespace SailCMS\Models;

use Exception;
use JsonException;
use League\Flysystem\FilesystemException;
use MongoDB\BSON\ObjectId;
use SailCMS\Cache;
use SailCMS\Collection;
use SailCMS\Contracts\Castable;
use SailCMS\Contracts\Validator;
use SailCMS\Database\Model;
use SailCMS\Debug;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\CollectionException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\Event;
use SailCMS\Http\Request;
use SailCMS\Locale;
use SailCMS\Log;
use SailCMS\Middleware;
use SailCMS\Middleware\Data;
use SailCMS\Middleware\Entry as MEntry;
use SailCMS\Sail;
use SailCMS\Search as SailSearch;
use SailCMS\Text;
use SailCMS\Types\Authors;
use SailCMS\Types\Dates;
use SailCMS\Types\EntryAlternate;
use SailCMS\Types\EntryFetchOption;
use SailCMS\Types\EntryParent;
use SailCMS\Types\Listing;
use SailCMS\Types\LocaleField;
use SailCMS\Types\MiddlewareType;
use SailCMS\Types\Pagination;
use SailCMS\Types\PublicationDates;
use SailCMS\Types\PublicationStatus;
use SailCMS\Types\QueryOptions;
use SailCMS\Validator as ContentValidator;
use SodiumException;
use stdClass;

/**
 *
 * @property string       $entry_type_id
 * @property ?EntryParent $parent
 * @property ?string      $site_id
 * @property string       $locale
 * @property Collection   $alternates
 * @property bool         $trashed = false
 * @property string       $title
 * @property string       $template
 * @property ?string      $slug
 * @property string       $url
 * @property Authors      $authors
 * @property Dates        $dates
 * @property Collection   $categories
 * @property Collection   $content
 *
 */
class Entry extends Model implements Validator, Castable
{
    protected string $collection = '';
    protected array $casting = [
        'parent' => EntryParent::class,
        'alternates' => self::class,
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
    public const TITLE_MISSING = ['5001: You must set the entry title in your data.', 5001];
    public const CANNOT_VALIDATE_CONTENT = ['5002: Can not validate the content because there is no entry layout assigned to the type.', 5002];
    public const TEMPLATE_NOT_SET = ['5003: Template property of the entry is not set.', 5003];
    public const CONTENT_KEY_ERROR = ['5004: The key "%s" does not exist in the schema of the entry layout.', 5004];
    public const CONTENT_ERROR = ['5005: The content has theses errors :' . PHP_EOL, 5005];
    public const DOES_NOT_EXISTS = ['5006: Entry "%s" does not exist.', 5006];
    public const DATABASE_ERROR = ['5007: Exception when "%s" an entry.', 5007];
    public const INVALID_LOCALE = ['5008: The "%s" locale is not set in the project.', 5008];
    public const ENTRY_PARENT_LIMIT_REACHED = ['5009: The parent can\'t be added because the limit of parent has been reached.', 5009];
    public const ENTRY_PARENT_INVALID = ['5010: The parent locale and siteId must be the same as the target entry.', 5010];
    public const ENTRY_PARENT_HOMEPAGE_ERROR = ['5011: Cannot add a parent to an homepage.', 5011];
    public const ENTRY_PARENT_ITSELF_ERROR = ['5012: Cannot add a parent to itself.', 5012];

    /* Cache */
    private const HOMEPAGE_CACHE = 'homepage_entry_';            // Add site id and locale at the end
    private const ONE_CACHE_BY_ID = 'entry_';                    // Add id at the end
    private const ENTRY_CACHE_BY_HANDLE_ALL = 'all_entry_';      // Add handle at the end
    private const ENTRY_FILTERED_CACHE = 'entries_filtered_';    // Add result of generateFilteredCacheKey
    private const ENTRY_CATEGORY_CACHE = 'entries_by_category_'; // Add category id

    private const PARENT_ENTRY_LIMIT = 2;

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
     * @param  string          $collection
     * @param  EntryType|null  $entryType
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
     * Create Publication Views for each entry type.
     *
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public static function generateAllViews(): void
    {
        $entryTypes = EntryType::getAll();

        $entryTypes->each(function ($k, $entryType)
        {
            /**
             * @var EntryType $entryType
             */
            $entryModel = $entryType->getEntryModel($entryType);
            $entryModel->generatePublicationView();
        });
    }

    /**
     *
     * Validate fields
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     * @throws EntryException
     *
     */
    public static function validate(string $key, mixed $value): void
    {
        if ($key === 'title' && empty($value)) {
            throw new EntryException(self::TITLE_MISSING[0], self::TITLE_MISSING[1]);
        }
    }

    /**
     *
     * Cast from for EntryAlternates collection
     *
     * @return array
     *
     */
    public function castFrom(): array
    {
        if (is_array($this->alternates)) {
            $this->alternates = new Collection($this->alternates);
        }

        $castedAlternates = [];
        if ($this->alternates) {
            $this->alternates->each(function ($key, $alternate) use (&$castedAlternates)
            {
                if ($alternate instanceof EntryAlternate) {
                    $castedAlternates[] = $alternate->castFrom();
                } // Else it's not an Entry Alternate object, so we ignore it
            });
        }

        return $castedAlternates;
    }

    /**
     *
     * Cast to for EntryAlternate elements
     *
     * @param  mixed  $value
     * @return EntryAlternate
     *
     */
    public function castTo(mixed $value): EntryAlternate
    {
        return (new EntryAlternate())->castTo($value);
    }

    /**
     *
     * Get basic entry document by its id
     *
     * @param  string|ObjectId  $id
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
     * Get the entry parent
     *
     * @return Entry|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function getParent(): ?Entry
    {
        $entry = null;

        if ($this->parent) {
            $entryType = (new EntryType())->getByHandle($this->parent->handle);
            $entry = $entryType->getEntryModel()->getById($this->parent->parent_id);
        }

        return $entry;
    }

    /**
     *
     * Get parent url
     *
     * @param  object|null       $currentHomepage
     * @param  EntryParent|null  $entryParent
     * @return string
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function getRecursiveParentUrls(?object $currentHomepage, ?EntryParent $entryParent = null): string
    {
        $url = "";

        if ($entryParent && $entryParent->handle && $entryParent->parent_id) {
            $entryType = (new EntryType())->getByHandle($entryParent->handle);
            $parent = $entryType->getEntryModel()->getById($entryParent->parent_id);
        } else {
            $parent = $this->getParent();
        }

        if ($parent) {
            $url .= $parent->getRecursiveParentUrls($currentHomepage);

            // If the parent is not the homepage
            if ($currentHomepage && $currentHomepage->{self::HOMEPAGE_CONFIG_ENTRY_KEY} !== (string)$parent->_id) {
                $url .= "/" . $parent->url;
            }
        } else {
            return $url;
        }
        return $url;
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
            ], false);
        }
        return $this->entryLayout;
    }

    /**
     *
     * Parse content according to EntryField type
     *
     * @param array $options
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws JsonException
     * @throws PermissionException
     */
    public function getContent(array $options = [EntryFetchOption::ALL->value]): Collection
    {
        $contentParsed = $this->content;

        // Replace id an asset or an entry for their object
        $schema = $this->getSchema(true);

        // Set element to fetch according to fetch options
        if (in_array(EntryFetchOption::ALL->value, $options)) {
            // Get ids to fetch
            $toFetch = [
                'assets' => [],
                'entries' => [],
                'categories' => []
            ];
        } else {
            $toFetch = [];
            foreach($options as $option) {
                $toFetch[$option] = [];
            }
        }

        // There is nothing to fetch
        if (empty($toFetch)) {
            return $contentParsed;
        }

        // Get ids to fetch
        $schema->each(function ($index, $fieldTab) use ($contentParsed, &$toFetch) {
            $fields =  $fieldTab->fields ? new Collection((array)$fieldTab->fields) : Collection::init();
            $this->getIdToFetchFromContent($fields, $toFetch, $contentParsed);
        });
        ray($toFetch);
        // Fetch data in batches
        $fetched = [];
        if (isset($toFetch[EntryFetchOption::ASSET->value])) {
            $result = Asset::getByIds($toFetch[EntryFetchOption::ASSET->value], false);
            $fetched[EntryFetchOption::ASSET->value] = new Collection($result);
        }
        if (isset($toFetch[EntryFetchOption::ENTRY->value])) {
            $result = (new EntryPublication())->getPublicationsByEntryIds(array_values($toFetch[EntryFetchOption::ENTRY->value]), true, false);
            $fetched[EntryFetchOption::ENTRY->value] = new Collection($result);
        }
        if (isset($toFetch[EntryFetchOption::CATEGORY->value])) {
            $result = Category::getByIds($toFetch[EntryFetchOption::CATEGORY->value]);
            $fetched[EntryFetchOption::CATEGORY->value] = new Collection($result);
        }

        // Parse content with fetched elements
        $contentParsed->each(function($key, &$content) use (&$contentParsed, $toFetch, $fetched) {
            if (is_object($content) && !$content instanceof Collection) {
                // It's not a collection, so it's a matrix content ~~~ todo be more precise - check in schema ?
                $matrixContent = Collection::init();
                foreach ($content as $matrixKey => $element) {
                    $contentFetched = $this->searchInFetchArray($key . "_" . $matrixKey, $element, $toFetch, $fetched);
                    $matrixContent->setFor($matrixKey, $contentFetched);
                }
                $contentParsed->setFor($key, $matrixContent);
            } else {
                // Any other type of content
                $contentFetched = $this->searchInFetchArray($key, $content, $toFetch, $fetched);
                $contentParsed->setFor($key, $contentFetched);
            }
        });

        return $contentParsed;
    }

    /**
     *
     * Recursively get id to fetch from content and a list of fields
     *
     * @param Collection $fields
     * @param array $toFetch
     * @param Collection $content
     * @param string $keyPrefix for matrix
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    private function getIdToFetchFromContent(Collection $fields, array &$toFetch, Collection $content, string $keyPrefix = ""): void
    {
        foreach($fields as $entryField) {
            $elementBaseKey = $keyPrefix . $entryField->key;
            /**
             * @var EntryField $entryField
             */
            switch ($entryField->type) {
                case "entry":
                    $fetchKey = EntryFetchOption::ENTRY->value;
                    break;
                case "asset_image":
                case "asset_file":
                    $fetchKey = EntryFetchOption::ASSET->value;
                    break;
                case "category":
                    $fetchKey = EntryFetchOption::CATEGORY->value;
                    break;
            }

            if ($entryField->type === "matrix") {
                $subFields = (new EntryField)->getFieldsForMatrix($entryField->_id);
                $matrixContent = new Collection((array)$content->get($entryField->key));
                $this->getIdToFetchFromContent($subFields, $toFetch, $matrixContent, $elementBaseKey . "_");
            } else if (isset($fetchKey) && isset($toFetch[$fetchKey]) && $content->get($entryField->key)) {
                $fieldContent = $content->get($entryField->key);
                if ($entryField->repeatable) {
                    foreach($fieldContent as $index => $element) {
                        $toFetch[$fetchKey][$elementBaseKey . "_" . $index] = $element;
                    }
                } else {
                    $toFetch[$fetchKey][$elementBaseKey] = $fieldContent;
                }
            }
        }
    }

    /**
     *
     * Search for Asset or Entry in fetched elements
     *
     * @param string $key
     * @param mixed $content
     * @param array $toFetch
     * @param array $fetched
     * @return mixed
     */
    private function searchInFetchArray(string $key, mixed $content, array $toFetch, array $fetched): mixed
    {
        $contentFetched = $content;
        if (is_array($content) || $content instanceof Collection) {
            $arrayContent = $content;
            foreach ($content as $index => $element) {
                // TODO dynamize that... ?
                if (isset($toFetch[EntryFetchOption::ENTRY->value]) && array_key_exists($key . "_" . $index, $toFetch[EntryFetchOption::ENTRY->value])) {
                    $entry = $fetched[EntryFetchOption::ENTRY->value]->find(fn($k, $c) => (string)$c->entry_id == $element);
                    $arrayContent[$index] = $entry ?? $element;
                } else if (isset($toFetch[EntryFetchOption::ASSET->value]) && array_key_exists($key . "_" . $index, $toFetch[EntryFetchOption::ASSET->value])) {
                    $asset = $fetched[EntryFetchOption::ASSET->value]->find(fn($k, $c) => (string)$c->_id === $element);
                    $arrayContent[$index] = $asset ?? $element;
                } else if (isset($toFetch[EntryFetchOption::CATEGORY->value]) && array_key_exists($key . "_" . $index, $toFetch[EntryFetchOption::CATEGORY->value])) {
                    $category = $fetched[EntryFetchOption::CATEGORY->value]->find(fn($k, $c) => (string)$c->_id === $element);
                    $arrayContent[$index] = $category ?? $element;
                }
            }
            $contentFetched = $arrayContent;
        } else {
            if (isset($toFetch[EntryFetchOption::ENTRY->value]) && array_key_exists($key, $toFetch[EntryFetchOption::ENTRY->value])) {
                $entry = $fetched[EntryFetchOption::ENTRY->value]->find(fn($k, $c) => (string)$c->entry_id == $content);
                $contentFetched = $entry ?? $content;
            } else if (isset($toFetch[EntryFetchOption::ASSET->value]) && array_key_exists($key, $toFetch[EntryFetchOption::ASSET->value])) {
                $asset = $fetched[EntryFetchOption::ASSET->value]->find(fn($k, $c) => (string)$c->_id === $content);
                $contentFetched = $asset ?? $content;
            } else if (isset($toFetch[EntryFetchOption::CATEGORY->value]) && array_key_exists($key, $toFetch[EntryFetchOption::CATEGORY->value])) {
                $category = $fetched[EntryFetchOption::CATEGORY->value]->find(fn($k, $c) => (string)$c->_id === $content);
                $contentFetched = $category ?? $content;
            }
        }
        return $contentFetched;
    }

    /**
     *
     * Process content before saving in database
     *
     * @param  stdClass|Collection  $content
     * @return array
     */
    private function processContentBeforeSave(stdClass|Collection $content): array
    {
        if ($content instanceof stdClass) {
            $content = new Collection((array)$content);
        }
//        $processedContents = [];
//        $schema = $this->getSchema(true);
//
//        $schema->each(function ($key, $modelField) use (&$processedContents, $content) {
//            $fieldContent = $content->get($key);
//
//            if ($fieldContent) {
//                /**
//                 * @var ModelField $modelField
//                 */
//                $processedFieldContent = $modelField->convert($fieldContent);
//
//                $processedContents[$key] = $processedFieldContent;
//            }
//        });

        return $content->unwrap();
    }

    /**
     *
     * Get All SEO data for this entry
     *
     * @param  bool  $refresh
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
     * @param  object|null  $currentHomepageEntry
     * @param  bool         $sendCurrent
     * @return array
     *
     */
    public function simplify(object|null $currentHomepageEntry, bool $sendCurrent = true): array
    {
        $isHomepage = false;
        if (isset($currentHomepageEntry) && isset($currentHomepageEntry->{self::HOMEPAGE_CONFIG_ENTRY_KEY})
            && (string)$this->_id === $currentHomepageEntry->{self::HOMEPAGE_CONFIG_ENTRY_KEY}) {
            $isHomepage = true;
        }
        $simplified = [
            '_id' => $this->_id,
            'entry_type' => $this->entryType->simplify(),
            'is_homepage' => $isHomepage,
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
            'content' => $this->content,
            'categories' => $this->categories->castFrom()
        ];

        if ($sendCurrent) {
            $simplified['current'] = $this;
        }

        return $simplified;
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

        $this->content->each(function ($key, $content) use (&$parsedContents, $schema)
        {
            $parsedContent = $content;

            $entryField = EntryLayout::getFieldInSchema($schema, $key);
            $isSearchable = $entryField->searchable ?? false;

            if ($isSearchable) {
                if (is_object($content)) {
                    if ($content instanceof Collection) {
                        $content = $content->unwrap();
                    }
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
     * @param  string       $entryTypeHandle
     * @param  string       $search
     * @param  int          $page
     * @param  int          $limit
     * @param  string       $sort
     * @param  int          $direction
     * @param  bool         $onlyTrash
     * @param  string|null  $locale
     * @return Listing
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public static function getList(string $entryTypeHandle, string $search = '', int $page = 1, int $limit = 50, string $sort = 'title', int $direction = Model::SORT_ASC, bool $onlyTrash = false, string $locale = null): Listing
    {
        $entryModel = EntryType::getEntryModelByHandle($entryTypeHandle);

        $offset = $page * $limit - $limit;

        $filters = [];

        // Only trash entries
        if ($onlyTrash) {
            $filters['trashed'] = true;
        } else {
            $filters['trashed'] = ['$in' => [false, null]];
        }

        // Specify locale
        if ($locale) {
            $filters['locale'] = $locale;
        }

        // Option for pagination
        $options = QueryOptions::initWithPagination($offset, $limit);
        $options->sort = [$sort => $direction];

        if ($search) {
            $searchResults = (new SailSearch())->search($search);
            $entryIds = [];
            $searchResults->results->each(function ($key, $searchResult) use (&$entryIds)
            {
                $entryIds[] = $searchResult->document_id;
            });

            $filters['_id'] = ['$in' => (new static())->ensureObjectIds($entryIds)->unwrap()];
        }

        // Actual query
        $cacheKey = self::generateCacheKeyFromFilters($entryTypeHandle, $filters) . '_' . $offset . '_' . $sort . '_' . $direction;
        $cacheTtl = setting('entry.cacheTtl', Cache::TTL_WEEK);
        $results = $entryModel->useView($entryModel->entryType->handle . '_entry_publication')->find($filters, $options)->exec($cacheKey, $cacheTtl);
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
     * @param  string  $entryTypeHandle
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

        $entries->each(function ($key, $entry)
        {
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
     * @param  string       $siteId
     * @param  string|null  $locale
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
     * Get all entries of given locale and type for listing purposes
     *
     * @param  string  $locale
     * @param  string  $entryTypeHandle
     * @param  string  $search
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public static function entriesForListing(string $locale, string $entryTypeHandle, string $search = ''): Collection
    {
        $result = EntryType::getEntryModelByHandle($entryTypeHandle)->all(false, ['locale' => $locale]);

        return $result->filter(function ($el) use ($search)
        {
            return (str_contains($el->title, $search));
        });
    }

    /**
     *
     * Get homepage entry !
     *
     * @param  string  $siteId
     * @param  string  $locale
     * @param  bool    $simplify
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
     * @param  string       $url
     * @param  string|null  $siteId
     * @param  bool         $fromRequest
     * @param  bool         $preview
     * @param  string       $previewVersion
     * @return Entry|array|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     * @throws CollectionException
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
     * @param  string       $categoryId
     * @param  string|null  $siteId
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

        $availableTypes->each(function ($key, $entryType) use ($categoryId, $siteId, &$allEntries)
        {
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
     * @param  LocaleField      $urlPrefix
     * @param  string           $slug
     * @param  string           $siteId
     * @param  string           $locale
     * @param  string|null      $currentId
     * @param  Collection|null  $availableTypes
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
        $slug = Text::from($slug)->slug($locale)->value();

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
        $availableTypes->each(function ($key, $value) use ($filters, &$found)
        {
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
     * @param  LocaleField  $urlPrefix
     * @param  string       $slug
     * @param  string       $locale
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
     * Combine and update content for GraphQL
     *
     * @param  string      $entryId
     * @param  Collection  $newContent
     * @return Collection
     * @throws DatabaseException
     *
     */
    public function updateContentForGraphQL(string $entryId, Collection $newContent): Collection
    {
        $entry = $this->findById($entryId)->exec();

        if (isset($entry->_id)) {
            $entry->content->each(function ($key, $content) use (&$newContent)
            {
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
        $newContent->each(function ($key, &$content) use (&$newContent)
        {
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
     * @param  Collection  $errors
     * @return Collection
     *
     */
    public static function processErrorsForGraphQL(Collection $errors): Collection
    {
        $parsedErrors = Collection::init();
        $errors->each(function ($key, $errors) use (&$parsedErrors)
        {
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
     * @param  array  $filters
     * @param  bool   $cache
     * @return Entry|null
     * @throws DatabaseException
     *
     */
    public function one(array $filters, bool $cache = false): Entry|null
    {
        $cacheTtl = $cache ? setting('entry.cacheTtl', Cache::TTL_WEEK) : 0;
        $cacheKey = $cache ? self::generateCacheKeyFromFilters($this->entryType->handle . "_one_", $filters) : '';
        $qs = $this->findOne($filters);

        if (isset($filters['_id'])) {
            $cacheKey = $cache ? self::ONE_CACHE_BY_ID . $filters['_id'] : '';
            $qs = $this->findById((string)$filters['_id']);
        }

        if (!$cache) {
            $this->clearCacheForModel();
            $entry = $qs->exec();
        } else {
            $entry = $qs->exec($cacheKey, $cacheTtl);
        }

        // Override type to get the good one
        $entry->entryType = $this->entryType;
        $entry->entry_type_id = $this->entry_type_id;

        // TODO: LOAD Objects for Ids (asset, assets, entry, entry list, etc.)

        return $entry;
    }

    /**
     *
     * Get the count according to given filters
     *
     * @param  array  $filters
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
     * @param  bool   $keepTrashed
     * @param ?array  $filters
     * @return Collection
     * @throws DatabaseException
     *
     */
    public function all(bool $keepTrashed = true, ?array $filters = []): Collection
    {
        // Fast selection of only valid entry (not thrashed)
        if (!$keepTrashed && !in_array('trashed', $filters)) {
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
     * @param  bool  $ignoreTrash
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
     * @param  bool              $isHomepage
     * @param  string            $locale
     * @param  string            $title
     * @param  string            $template
     * @param  string|null       $slug
     * @param  array|Collection  $extraData
     * @param  bool              $throwErrors
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
            'slug' => $slug,
            'isHomepage' => $isHomepage
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
     * @param  Entry|string      $entry  or id
     * @param  array|Collection  $data
     * @param  bool              $throwErrors
     * @param  bool              $bypassValidation
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
    public function updateById(Entry|string $entry, array|Collection $data, bool $throwErrors = true, bool $bypassValidation = false): Collection
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
            throw new EntryException(sprintf(self::DOES_NOT_EXISTS[0], $entryId), self::DOES_NOT_EXISTS[1]);
        }

        $updateErrors = $this->updateWithoutPermission($entry, $data, $throwErrors, $bypassValidation);

        if ($updateErrors->length <= 0) {
            $this->handleHomepageUpdate($entry, $data);
        }

        return $updateErrors;
    }

    /**
     *
     * Update entries url according to an url prefix (normally comes from entry type)
     *
     * @param  LocaleField  $urlPrefix
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
     * @param  string  $key
     * @param  string  $newKey
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
        $entries->each(function ($k, $entry) use (&$updates, $key, $newKey)
        {
            $newContent = Collection::init();
            $entry->content->each(function ($currentKey, $content) use (&$newContent, $key, $newKey)
            {
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
                $errorMsg = sprintf(self::DATABASE_ERROR[0], "bulk update content") . PHP_EOL . $exception->getMessage();
                throw new EntryException($errorMsg, self::DATABASE_ERROR[1]);
            }
        }
        return true;
    }

    /**
     *
     * Create an entry version then an entry publication
     *
     * @param  string       $entryId
     * @param  int          $publicationDate
     * @param  int          $expirationDate
     * @param  string|null  $siteId
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
        $author = User::$currentUser ?? User::anonymousUser();

        if (!$siteId) {
            $siteId = Sail::siteId();
        }

        $entryVersionModel = new EntryVersion();
        $lastVersion = $entryVersionModel->getLastVersionByEntryId($entryId);

        // It is almost impossible that an entry has no version, but just to be sure
        if (!$lastVersion) {
            $entry = $this->findById($entryId)->exec();
            $simplifiedEntry = $entry->simplify(null, false);
            $simplifiedEntry['content'] = $entry->content;
            $entryVersionID = (new EntryVersion)->create($author, $simplifiedEntry);

            $lastVersion = $entryVersionModel->getLastVersionByEntryId($entryId);
        }

        $entryVersionID = !isset($entryVersionID) ? $lastVersion->_id : $entryVersionID;
        $entryUrl = $lastVersion->entry->get('url');
        $entryParent = (new EntryParent())->castTo($lastVersion->entry->get('parent'));

        // Must override entryUrl if it's the homepage
        $currentHomepage = self::getHomepage($siteId, $lastVersion->entry->get('locale'));
        if ($currentHomepage && $currentHomepage->{self::HOMEPAGE_CONFIG_ENTRY_KEY} === $entryId) {
            $entryLocale = $lastVersion->entry->get('locale');
            $entryUrl = "";
            if ($entryLocale !== Locale::default()) {
                $entryUrl = $entryLocale . "/";
            }
        } else {
            $parentUrl = $this->getRecursiveParentUrls($currentHomepage, $entryParent);
            $entryUrl = $parentUrl ? $parentUrl . "/" . $entryUrl : $entryUrl;
        }

        return (new EntryPublication())->create($author, $entryId, $siteId, $entryUrl, (string)$entryVersionID, $publicationDate, $expirationDate);
    }

    /**
     *
     * Remove all entry publication to unpublish
     *
     * @param  string  $entryId
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
     * @param  string|ObjectId  $entryId
     * @param  bool             $soft
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
            throw new EntryException(sprintf(self::DOES_NOT_EXISTS[0], $entryId), self::DOES_NOT_EXISTS[1]);
        }

        if ($soft) {
            $result = $this->softDelete($entry);
        } else {
            $result = $this->hardDelete($entryId);
        }

        // Update homepage if needed
        if ($result) {
            $currentHomepages = self::getHomepage($entry->site_id);
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
     * @param  bool  $silent
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function getSchema(bool $silent = false): Collection
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
                Log::error($errorMessage, ['entry' => $this]);
            }
        }

        $result = $entryLayout ? $entryLayout->schema : Collection::init();

        if (is_array($result)) {
            $result = new Collection($result);
        }

        return $result;
    }

    /**
     *
     * Validate entry parent before saving it
     *
     * @param  EntryParent  $entryParent
     * @param  string       $entryLocale
     * @param  string       $entrySiteId
     * @param  string|null  $entryId
     * @param  bool         $isHomepage
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SodiumException
     *
     */
    private function validateParent(EntryParent $entryParent, string $entryLocale, string $entrySiteId, ?string $entryId = null, bool $isHomepage = false): void
    {
        $currentHomepage = self::getHomepage($entrySiteId, $entryLocale);
        $entryModel = EntryType::getEntryModelByHandle($entryParent->handle);
        $parent = $entryModel->getById($entryParent->parent_id);
        $errorContext = ["parent" => $parent, "entryId" => $entryId, "entryLocale" => $entryLocale, "entrySiteId" => $entrySiteId];

        // Test if parent exists
        if (!$parent) {
            $errorMsg = sprintf(self::DOES_NOT_EXISTS[0], $entryParent->parent_id . "(" . $entryParent->handle . ")");
            throw new EntryException($errorMsg, self::DOES_NOT_EXISTS[1]);
        }

        // Test if the entry is a homepage
        if (($entryId && $currentHomepage->{self::HOMEPAGE_CONFIG_ENTRY_KEY} === $entryId) || $isHomepage) {
            throw new EntryException(self::ENTRY_PARENT_HOMEPAGE_ERROR[0], self::ENTRY_PARENT_HOMEPAGE_ERROR[1]);
        }

        // Test if entry and parent is the same
        if ($entryId && $entryId === (string)$parent->_id) {
            throw new EntryException(self::ENTRY_PARENT_ITSELF_ERROR[0], self::ENTRY_PARENT_ITSELF_ERROR[1]);
        }

        // Test if same locale and site
        if ($parent->locale !== $entryLocale || $parent->site_id !== $entrySiteId) {
            throw new EntryException(self::ENTRY_PARENT_INVALID[0], self::ENTRY_PARENT_INVALID[1]);
        }

        // Test if child + parent lower than self::PARENT_ENTRY_LIMIT
        $childCount = $entryId ? $this->countMaxChildren($entryParent->parent_id) : 0;
        $parentCount = $this->countParent($parent);

        if ($parentCount + $childCount >= self::PARENT_ENTRY_LIMIT) {
            if ($parentCount + $childCount > self::PARENT_ENTRY_LIMIT) {
                Log::warning("PARENT_ENTRY_LIMIT exceeded", $errorContext);
            }
            throw new EntryException(self::ENTRY_PARENT_LIMIT_REACHED[0], self::ENTRY_PARENT_LIMIT_REACHED[1]);
        }
    }

    /**
     *
     * Recursive count of children until we reach the limit
     *
     * @param  string  $entryId
     * @return int
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function countMaxChildren(string $entryId): int
    {
        $count = 0;
        $filters = [
            "parent.parent_id" => $entryId
        ];

        $availableTypes = EntryType::getAll();
        $availableTypes->each(function ($key, $entryType) use ($filters, &$count)
        {
            if ($count >= self::PARENT_ENTRY_LIMIT) {
                // The limit is reached, not useful to continue the count
                return;
            }

            /**
             * @var EntryType $entryType
             */
            $entryModel = $entryType->getEntryModel();

            $result = $entryModel->all(false, $filters);

            foreach ($result as $child) {
                if ($count === 0) {
                    $count = 1;
                }

                $currentCount = $this->countMaxChildren($child->_id);
                if ($currentCount > 0) {
                    $count += $currentCount;
                }

                if ($count >= self::PARENT_ENTRY_LIMIT) {
                    // The limit is reached, not useful to continue the count
                    break;
                }
            }
        });

        return $count;
    }

    /**
     *
     * Count parent until it reach the PARENT_ENTRY_LIMIT
     *
     * @param  Entry     $entry
     * @param  int|null  $count
     * @return int
     *
     */
    public function countParent(Entry $entry, ?int $count = null): int
    {
        if (!isset($count)) {
            $count = 0;
        }

        if ($entry->parent) {
            try {
                $entryModel = EntryType::getEntryModelByHandle($entry->parent->handle);
                $parent = $entryModel->getById($entry->parent->parent_id);
            } catch (Exception $exception) {
                Log::warning("Error when a parent is queried" . PHP_EOL, ['exception' => $exception]);

                // If there is an error with the queries return the limit, so the parent will not be added.
                return self::PARENT_ENTRY_LIMIT;
            }

            if ($parent) {
                $count += 1;

                if ($count >= self::PARENT_ENTRY_LIMIT) {
                    // Stop the recursion since the limit is reached
                    return $count;
                }

                $count = $this->countParent($parent, $count);
            }
        }

        return $count;
    }

    /**
     *
     * Get count of all content that are from given types
     *
     * @param  Collection  $types
     * @return int
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public static function countAllThatAre(Collection $types): int
    {
        $total = 0;

        foreach ($types as $type) {
            /**
             * @var EntryType $type
             */
            $model = $type->getEntryModel($type);
            $total += $model->count([]);
        }

        return $total;
    }

    /**
     *
     * Create publication view with the current instance
     *
     * @return void
     *
     */
    public function generatePublicationView(): void
    {
        $modelPublication = new EntryPublication();
        $viewName = $this->entryType->handle . '_entry_publication';
        $pipeline = [
            [
                '$lookup' => [
                    'from' => $modelPublication->getCollection(),
                    'let' => ['entryId' => '$_id'],
                    'pipeline' => [
                        [
                            '$match' => [
                                '$expr' => [
                                    '$eq' => [
                                        '$entry_id',
                                        [
                                            '$toString' => '$$entryId'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'as' => $viewName,
                ]
            ]
        ];

        try {
            $this->createView($viewName, $pipeline);
        } catch (Exception $exception) {
            Log::warning("View '$viewName' cannot be created.", ['error' => $exception, 'context' => $this]);
        }
    }

    /**
     *
     * Validate content from the entry type layout schema
     *
     * @param  Collection  $contents
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    private function validateContent(Collection $contents): Collection
    {
        $errors = Collection::init();

        $schema = null;
        if ($this->entryType->entry_layout_id) {
            $schema = $this->getSchema(true);
        }

        if ($contents->length > 0 && !$schema) {
            Log::error(self::CANNOT_VALIDATE_CONTENT[0], ['entry' => $this]);
        }

        // Create an empty schema if empty
        if (!$schema) {
            $schema = Collection::init();
        }

        // Validate content from schema
        $contents->each(function ($key, $content) use ($schema, $errors, $contents)
        {
            $entryField = EntryLayout::getFieldInSchema($schema, $key);
            $contentFieldErrors = Collection::init();

            if (!$entryField) {
                // Fail silently to avoid an error on saving
                Log::warning(sprintf(self::CONTENT_KEY_ERROR[0], $key), ['contents' => $contents, 'schema' => $schema]);
                return false;
            }

            if ($entryField->required && !ContentValidator::required($content)) {
                $contentFieldErrors->push(EntryField::FIELD_REQUIRED);
            } else {
                $failedValidations = ContentValidator::validateContentWithEntryField($content, $entryField, true);
                if ($failedValidations->length > 0) {
                    $contentFieldErrors->merge($failedValidations);
                }
            }

            if ($contentFieldErrors->length > 0) {
                $errors->pushKeyValue($key, $contentFieldErrors);
            }
            return true;
        });

        return $errors;
    }

    /**
     *
     * Handle homepage config after update
     *
     * @param  Entry       $oldEntry
     * @param  Collection  $newData
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
        if (($newSiteId && $newSiteId !== $currentSiteId) || ($newLocale && $newLocale !== $currentLocale) || $homepageChange === true) {
            // Remove homepage
            $this->emptyHomepage($oldEntry->site_id, $oldEntry->locale);
            // Add homepage
            $oldEntry->setAsHomepage($currentSiteId, $currentLocale);
        } else {
            if ($homepageChange === false) {
                // Remove homepage
                $this->emptyHomepage($oldEntry->site_id, $oldEntry->locale);
            }
        }
    }

    /**
     *
     * Set the current entry has homepage
     *
     * @param  string  $siteId
     * @param  string  $locale
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
     * @param  string             $siteId
     * @param  string             $locale
     * @param  object|array|null  $currentConfig
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
     *
     * @param  Collection|array  $data
     * @param  bool              $throwErrors
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
    private function createWithoutPermission(Collection|array $data, bool $throwErrors = true): array|Entry|Collection|null
    {
        $locale = $data->get('locale');
        $title = $data->get('title');
        $template = $data->get('template');
        $slug = $data->get('slug', Text::from($title)->slug($locale)->value());
        $site_id = $data->get('site_id', Sail::siteId());
        $author = User::$currentUser ?? User::anonymousUser();
        $alternates = $data->get('alternates', []);
        $parent = $data->get('parent');
        $content = $data->get('content');
        $categories = $data->get('categories');

        if ($parent) {
            $parent = (new EntryParent())->castTo($parent);
            $this->validateParent($parent, $locale, $site_id, null, $data->get('isHomepage', false));
        }

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

            $content = $this->processContentBeforeSave($content);
        }

        // Validate locale
        $locales = Locale::getAvailableLocales();
        if (!$locales->contains($locale)) {
            $errorMsg = sprintf(self::INVALID_LOCALE[0], $locale);
            throw new EntryException($errorMsg, self::INVALID_LOCALE[1]);
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
            $errorMsg = sprintf(self::DATABASE_ERROR[0], "creating") . PHP_EOL . $exception->getMessage();
            throw new EntryException($errorMsg, self::DATABASE_ERROR[1]);
        }

        $entry = $this->findById($entryId)->exec();
        // The query has the good entry type
        $entry->entryType = $this->entryType;

        // Version save with simplify entry
        $simplifiedEntry = $entry->simplify(null, false);
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
     * @param  Entry       $entry
     * @param  Collection  $data
     * @param  bool        $throwErrors
     * @param  bool        $bypassValidation
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
    private function updateWithoutPermission(Entry $entry, Collection $data, bool $throwErrors = true, bool $bypassValidation = false): Collection
    {
        $update = [];
        $author = User::$currentUser ?? User::anonymousUser();
        $slug = $entry->slug;
        $locale = $entry->locale;
        $site_id = $entry->site_id;

        // Data format
        if (in_array('site_id', $data->keys()->unwrap())) {
            $site_id = $data->get('site_id');
        }
        if (in_array('slug', $data->keys()->unwrap())) {
            $slug = $data->get('slug');
            $update['slug'] = self::getValidatedSlug($this->entryType->url_prefix, $slug, $site_id, $locale, $entry->_id);
        }

        // Validation could be bypassed when the version is applied or with an attribute in the graphql query.
        if (!$bypassValidation) {
            $locales = Locale::getAvailableLocales();

            if (in_array('alternates', $data->keys()->unwrap())) {
                $alternates = $data->get('alternates');
                $alternates->each(function ($key, $alternate) use ($locales)
                {
                    if (isset($alternate->locale) && !$locales->contains($alternate->locale)) {
                        $errorMsg = sprintf(Entry::INVALID_LOCALE[0], $alternate->locale);
                        throw new EntryException($errorMsg, Entry::INVALID_LOCALE[1]);
                    }
                });
            }

            if (in_array('locale', $data->keys()->unwrap())) {
                $locale = $data->get('locale');
                // Validate locale
                if (!$locales->contains($locale)) {
                    $errorMsg = sprintf(self::INVALID_LOCALE[0], $locale);
                    throw new EntryException($errorMsg, self::INVALID_LOCALE[1]);
                }
            }

            if (in_array('parent', $data->keys()->unwrap())) {
                $entryParent = (new EntryParent())->castTo($data->get('parent'));
                $this->validateParent($entryParent, $locale, $site_id, (string)$entry->_id);
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
        }

        if ($data->get('content')) {
            // Add it to the update
            $update['content'] = $this->processContentBeforeSave($data->get('content'));
        }

        $data->each(function ($key, $value) use (&$update)
        {
            if (in_array($key, ['parent', 'site_id', 'locale', 'title', 'template', 'categories', 'alternates'])) {
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
            $errorMsg = sprintf(self::DATABASE_ERROR[0], "updating") . PHP_EOL . $exception->getMessage();
            throw new EntryException($errorMsg, self::DATABASE_ERROR[1]);
        }

        // Could we avoid to get the entry ?
        $entry = $this->findById($entry->_id)->exec();
        // The query has the good entry type
        $entry->entryType = $this->entryType;

        // Version save with simplified entry
        $simplifiedEntry = $entry->simplify(null, false);
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
     * @param  Entry  $entry
     * @return bool
     * @throws EntryException
     * @throws DatabaseException
     *
     */
    private function softDelete(Entry $entry): bool
    {
        $user = User::$currentUser ?? User::anonymousUser();

        $authors = Authors::deleted($entry->authors, $user->_id);
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
            $errorMsg = sprintf(self::DATABASE_ERROR[0], "soft deleting") . PHP_EOL . $exception->getMessage();
            throw new EntryException($errorMsg, self::DATABASE_ERROR[1]);
        }

        return $qtyUpdated === 1;
    }

    /**
     *
     * Delete an entry definitively
     *
     * @param  string|ObjectId  $entryId
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
            $errorMsg = sprintf(self::DATABASE_ERROR[0], "hard deleting") . PHP_EOL . $exception->getMessage();
            throw new EntryException($errorMsg, self::DATABASE_ERROR[1]);
        }

        // Must delete seo data
        try {
            (new EntrySeo())->deleteByEntryId((string)$entryId, false);
        } catch (Exception $e) {
            // Do nothing because there is no entry seo for this entry
        }

        // And publications
        (new EntryPublication())->deleteAllByEntryId((string)$entryId, false);

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
     * @param  string       $url
     * @param  string|null  $siteId
     * @return Entry|null
     * @throws ACLException
     * @throws CollectionException
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
            $entryType = $publication->version->entry->get('entry_type');

            if (!$entryType) {
                // Add a deprecation message
                $entryTypeId = $publication->version->entry->get('entry_type_id');
            } else {
                $entryTypeId = $entryType->_id;
            }

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
     * @param  string       $url
     * @param  string|null  $previewVersion
     * @return Entry|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     * @throws CollectionException
     *
     */
    private static function findByUrlFromEntryTypes(string $url, ?string $previewVersion): ?Entry
    {
        // Load all entry types before scanning them
        $availableTypes = EntryType::getAll();
        $content = null;

        $availableTypes->each(function ($key, $value) use ($url, $previewVersion, &$content)
        {
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
     * @param  Collection  $errors
     * @return void
     * @throws EntryException
     *
     */
    private static function throwErrorContent(Collection $errors): void
    {
        $errorsStrings = [];

        $errors->each(function ($key, $errorsArray) use (&$errorsStrings)
        {
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
            $errorMsg = self::CONTENT_ERROR[0] . implode("\t," . PHP_EOL, $errorsStrings);
            throw new EntryException($errorMsg, self::CONTENT_ERROR[1]);
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
     * @param  string            $handle
     * @param  Collection|array  $filters
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
     * @param  mixed  $iterableOrValue
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
