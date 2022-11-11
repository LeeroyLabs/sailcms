<?php

namespace SailCMS\Models;

use JsonException;
use League\Flysystem\FilesystemException;
use MongoDB\BSON\ObjectId;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\Http\Request;
use SailCMS\Sail;
use SailCMS\Text;
use SailCMS\Types\Authors;
use SailCMS\Types\Dates;
use SailCMS\Types\EntryStatus;
use SodiumException;

class Entry extends BaseModel
{
    /* Homepage config */
    const HOMEPAGE_CONFIG_HANDLE = 'homepage';
    const HOMEPAGE_CONFIG_ENTRY_TYPE_KEY = 'entry_type_handle';
    const HOMEPAGE_CONFIG_ENTRY_KEY = 'entry_id';

    /* Errors */
    const TITLE_MISSING = 'You must set the entry title in your data';
    const DATABASE_ERROR = 'Exception when %s an entry';

    /* Fields */
    public string $entry_type_id;
    public ?string $parent_id;
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

    // TODO: populate CONTENT

    /**
     *
     *  Get the model according to the collection
     *
     * @throws DatabaseException
     * @throws EntryException
     */
    public function __construct(string $collection = '')
    {
        // Get or create the default entry type
        if (!$collection) {
            $this->entryType = EntryType::getDefaultType();
        } else {
            // Get entry type by collection name
            $this->entryType = EntryType::getByCollectionName($collection);
        }

        $this->entry_type_id = $this->entryType->_id;
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
     * @param  bool  $fetchAllFields
     * @return string[]
     *
     */
    public function fields(bool $fetchAllFields = false): array
    {
        return [
            '_id',
            'entry_type_id',
            'parent_id',
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
     * Get all entries of all types
     *
     * @return Collection
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    public static function getAll(): Collection
    {
        $entryTypes = EntryType::getAll();
        $entries = new Collection([]);

        $entryTypes->each(function ($key, $value) use (&$entries)
        {
            /**
             * @var EntryType $value
             */
            $entryModel = $value->getEntryModel();
            $currentEntries = $entryModel->all();

            $entries->pushSpread(...$currentEntries);
        });

        return $entries;
    }

    /**
     *
     * Get homepage
     *
     * @param  bool  $getEntry
     * @return array|object|null
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws SodiumException
     */
    public static function getHomepage(bool $getEntry = false): array|object|null
    {
        $homepageConfig = Config::getByName(self::HOMEPAGE_CONFIG_HANDLE);

        if ($getEntry) {
            $currentSiteHomepage = $homepageConfig->config->{Sail::siteId()} ?? null;
            if (!$currentSiteHomepage) {
                // TODO log !
                return null;
            }
            print_r($currentSiteHomepage);
            $entryModel = EntryType::getEntryModelByHandle($currentSiteHomepage->{self::HOMEPAGE_CONFIG_ENTRY_TYPE_KEY});

            return $entryModel->findById($currentSiteHomepage->{self::HOMEPAGE_CONFIG_ENTRY_KEY})->exec();
        }
        return $homepageConfig->config;
    }

    /**
     *
     * Find a content by the url
     *
     * @param  string  $url
     * @param  bool    $fromRequest
     * @return Entry|null
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    public static function findByURL(string $url, bool $fromRequest = true): ?Entry
    {
        // Load all entry types before scanning them
        $availableTypes = EntryType::getAll();
        $request = $fromRequest ? new Request() : null;
        $content = null;

        $availableTypes->each(function ($key, $value) use ($url, $request, &$content)
        {
            // We already have it, stop!
            if ($content !== null) {
                return;
            }

            // Search for what collection has this url (if any)
            $entry = new Entry($value->collection_name);
            $found = $entry->count(['url' => $url, 'site_id' => Sail::siteId()]);

            if ($found > 0) {
                // Winner Winner Chicken Diner!
                $content = $entry->findOne(['url' => $url, 'site_id' => Sail::siteId()])->exec();

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
     * Get a validated slug that is not already existing in the db
     *
     * @param  string           $url_prefix
     * @param  string           $slug
     * @param  string           $site_id
     * @param  string           $locale
     * @param  string|null      $currentId
     * @param  Collection|null  $availableTypes
     * @return string
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    public static function getValidatedSlug(string $url_prefix, string $slug, string $site_id, string $locale, ?string $currentId = null, Collection $availableTypes = null): string
    {
        // Just to be sure that the slug is ok
        $slug = Text::slugify($slug, $locale);

        // Form the url to find if it already exists
        $url = self::getRelativeUrl($url_prefix, $slug);
        $found = 0;

        // Set the filters for the query
        $filters = ['url' => $url, 'site_id' => $site_id];
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
            return self::getValidatedSlug($url_prefix, $slug, $site_id, $locale, $currentId, $availableTypes);
        }
        return $slug;
    }

    /**
     *
     * Get the relative url of the entry
     *
     * @param $url_prefix
     * @param $slug
     * @return string
     *
     */
    public static function getRelativeUrl($url_prefix, $slug): string
    {
        $relativeUrl = "";

        if ($url_prefix) {
            $relativeUrl .= $url_prefix . '/';
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
            $newSlug = $matches['base'] . $increment + 1;
        } else {
            $newSlug = $slug . "-2";
        }

        return $newSlug;
    }

    /**
     *
     * Get an entry with filters
     *
     * @param  array  $filters
     * @return Entry|null
     * @throws DatabaseException
     *
     */
    public function one(array $filters): Entry|null
    {
        if (isset($filters['_id'])) {
            return $this->findById($filters['_id'])->exec();
        }

        return $this->findOne($filters)->exec();
    }

    /**
     *
     * Get all entries of the current type
     *  with filtering and pagination
     *
     * @param  Collection|null  $filters
     * @param  int|null         $limit
     * @param  int|null         $offset
     * @return Collection
     *
     * @throws DatabaseException
     */
    public function all(?Collection $filters = null, ?int $limit = 0, ?int $offset = 0): Collection
    {
        // Filters available date, author, category, status

        return new Collection($this->find([])->exec());
    }

    /**
     *
     * Create an entry
     *  The extra data can contains:
     *      - parent_id default null
     *      - authors default User::currentUser
     *      - categories default empty Collection
     *      - content default empty Collection
     *
     * TODO handle site_id, alternates
     *
     * @param  bool                $is_homepage
     * @param  string              $locale
     * @param  EntryStatus|string  $status
     * @param  string              $title
     * @param  string|null         $slug
     * @param  array|Collection    $optionalData
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
    public function createOne(bool $is_homepage, string $locale, EntryStatus|string $status, string $title, ?string $slug = null, array|Collection $optionalData = []): array|Entry|null
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
        if (is_array($optionalData)) {
            $data->merge(new Collection($optionalData));
        }

        $entry = $this->create($data);

        if ($is_homepage) {
            print_r($entry->title . " - " . $entry->entryType->handle);
            $entry->setAsHomepage();
        }

        return $entry;
    }

    /**
     *
     * Update an entry with a given entry id or entry instance
     *
     * @param  Entry|string      $entry
     * @param  array|Collection  $data
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SodiumException
     */
    public function updateById(Entry|string $entry, array|Collection $data): bool
    {
        $this->hasPermissions();

        if (is_string($entry)) {
            $entry = $this->findById($entry);
        }
        if (is_array($data)) {
            $data = new Collection($data);
        }

        $updateResult = $this->update($entry, $data);

        // Update homepage if needed
        if ($updateResult) {
            $is_homepage = $data->get('homepage');
            $currentHomepages = Entry::getHomepage();
            $currentHomepage = $currentHomepages->{Sail::siteId()} ?? false;
            if ($currentHomepage) {
                if ($is_homepage && $currentHomepage->{self::HOMEPAGE_CONFIG_ENTRY_KEY} !== (string)$entry->_id) {
                    $entry->setAsHomepage();
                } else {
                    if ($is_homepage === false && $currentHomepage->{self::HOMEPAGE_CONFIG_ENTRY_KEY} !== (string)$entry->_id) {
                        $this->emptyHomepage($currentHomepages);
                    }
                }
            }
        }

        return $updateResult;
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

        if ($soft) {
            $entry = $this->findById($entryId);
            $result = $this->softDelete($entry);
        } else {
            $result = $this->hardDelete($entryId);
        }

        // Update homepage if needed
        if ($result) {
            $currentHomepages = Entry::getHomepage();
            $currentHomepage = $currentHomepages->{Sail::siteId()} ?? false;
            if ($currentHomepage && $currentHomepage->{self::HOMEPAGE_CONFIG_ENTRY_KEY} === (string)$entryId) {
                $this->emptyHomepage($currentHomepages);
            }
        }

        return $result;
    }

    /**
     *
     * Process authors and dates fields
     *
     * @param  string  $field
     * @param  mixed   $value
     * @return mixed
     *
     */
    protected function processOnFetch(string $field, mixed $value): mixed
    {
        return match ($field) {
            "authors" => new Authors($value->created_by, $value->updated_by, $value->published_by, $value->deleted_by),
            "dates" => new Dates($value->created, $value->updated, $value->published, $value->deleted),
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
     * Set the current entry has homepage
     *
     * @return void
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws JsonException
     * @throws SodiumException
     *
     */
    private function setAsHomepage(): void
    {
        Config::setByName(self::HOMEPAGE_CONFIG_HANDLE, [
            Sail::siteId() => [
                self::HOMEPAGE_CONFIG_ENTRY_KEY => (string)$this->_id,
                self::HOMEPAGE_CONFIG_ENTRY_TYPE_KEY => $this->entryType->handle
            ]
        ]);
    }

    /**
     *
     * Empty the homepage for the current site
     *
     * @param  array  $currentConfig
     * @return void
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws JsonException
     * @throws SodiumException
     *
     */
    private function emptyHomepage(object|array $currentConfig): void
    {
        $currentConfig->{Sail::siteId()} = null;
        Config::setByName(self::HOMEPAGE_CONFIG_HANDLE, $currentConfig);
    }

    /**
     *
     * Create an entry
     *
     * @param  Collection  $data
     * @return array|Entry|null
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    private function create(Collection $data): array|Entry|null
    {
        $locale = $data->get('locale');
        $status = $data->get('status', EntryStatus::INACTIVE->value);
        $title = $data->get('title');
        $slug = $data->get('slug', Text::slugify($title, $locale));
        $site_id = $data->get('site_id', Sail::siteId());
        $author = User::$currentUser;
        // TODO implements others fields: parent_id categories content alternates

        // Get the validated slug just to be sure
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
                'site_id' => $site_id,
                'locale' => $locale,
                'status' => $status,
                'title' => $title,
                'slug' => $slug,
                'url' => self::getRelativeUrl($this->entryType->url_prefix, $slug),
                'authors' => $authors,
                'dates' => $dates,
                // TODO
                'parent_id' => null,
                'alternates' => new Collection([]),
                'categories' => new Collection([]),
                'content' => new Collection([])
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
     * Update an entry
     *
     * @param  Entry       $entry
     * @param  Collection  $data
     * @return bool
     * @throws EntryException
     * @throws DatabaseException
     *
     */
    private function update(Entry $entry, Collection $data): bool
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

        $data->each(function ($key, $value) use (&$update)
        {
            if (in_array($key, ['parent_id', 'site_id', 'locale', 'status', 'title', 'categories', 'content'])) {
                $update[$key] = $value;
            }
        });

        // Automatic attributes
        // TODO generate alternates
        $update['url'] = self::getRelativeUrl($this->entryType->url_prefix, $slug);
        $update['authors'] = Authors::updated($entry->authors, User::$currentUser->_id);
        $update['dates'] = Dates::updated($entry->dates);

        try {
            $qtyUpdated = $this->updateOne(['_id' => $entry->_id], [
                '$set' => $update
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'updating') . PHP_EOL . $exception->getMessage());
        }

        return $qtyUpdated === 1;
    }

    /**
     *
     * Put an entry in the trash
     *
     * @param  Entry  $entry
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
}
