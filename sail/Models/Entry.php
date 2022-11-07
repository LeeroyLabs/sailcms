<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Http\Request;
use SailCMS\Sail;
use SailCMS\Types\Authors;
use SailCMS\Types\Dates;
use SailCMS\Types\EntryStatus;

class Entry extends BaseModel
{
    /* Errors */
    const TITLE_MISSING = "You must set the entry title in your data";
    const SLUG_MISSING = "You must set the entry slug in your data";
    const HOMEPAGE_ALREADY_EXISTS = "Your project has already an homepage that is live";
    const URL_NOT_AVAILABLE = "The %s url is not available";
    const CANNOT_CREATE_ENTRY = "You don't have the right to create an entry";
    const DATABASE_ERROR = "Exception when %s an entry";

    /* Fields */
    public string $entry_type_id;
    public ?string $parent_id;
    public ?string $site_id;
    public string $locale;
    public Collection $alternates; // Array of object "locale" -> "lang_code", "entry" -> "entry_id"
    public bool $is_homepage;
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

    public function fields(bool $fetchAllFields = false): array
    {
        return [
            '_id',
            'entry_type_id',
            'parent_id',
            'site_id',
            'locale',
            'alternates',
            'is_homepage',
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

        $filters = ['url' => $url, 'status' => EntryStatus::LIVE->value];
        if ($url === '' || $url === '/') {
            $filters = ['is_homepage' => true, 'status' => EntryStatus::LIVE->value];
            $url = '/';
        }

        $availableTypes->each(function ($key, $value) use ($filters, $url, $request, &$content)
        {
            // We already have it, stop!
            if ($content !== null) {
                return;
            }

            // Search for what collection has this url (if any)
            $entry = new Entry($value->collection_name);
            $found = $entry->count($filters);

            if ($found > 0) {
                // Winner Winner Chicken Diner!
                $content = $entry->findOne(['url' => $url, 'status' => EntryStatus::LIVE->value])->exec();

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

                if ($preview !== null && $previewVersion !== null && EntryStatus::from($content->status) === EntryStatus::LIVE) {
                    // Page is not published but preview mode is active
                    if ($preview) {
                        // TODO: HANDLE PREVIEW
                        //$content = null;
                    }

                    // Page exists but is not published
                    $content = null;
                }
            }
        });

        return $content;
    }

    /**
     *
     * Get an entry with filters
     *
     * @param $filters
     * @return Entry|null
     * @throws DatabaseException
     *
     */
    public function one($filters): Entry|null
    {
        // TODO what to do with options

        return $this->findOne($filters)->exec();
    }

    /**
     *
     * Get all with filtering and pagination
     *
     * @param $filters
     * @param $limit
     * @param $offset
     * @return array
     *
     */
    public function all($filters, $limit, $offset): array
    {
        // Filters available date, author, category, status
        return [];
    }

    /**
     *
     * Validate the url
     *
     * @throws EntryException
     * @throws DatabaseException
     *
     */
    private function validateUrlAvailability(string $status, bool $isHomepage, ?string $slug, ?string $currentId = null)
    {
        if ($status == EntryStatus::LIVE->value) {
            $filters = ['is_homepage' => true, 'status' => EntryStatus::LIVE->value];

            if ($currentId) {
                $filters['_id'] = ['$ne' => new ObjectId($currentId)];
            }

            // TODO THAT FOR EACH entry COLLECTION
            $hasHomepage = $this->count($filters);

            if ($isHomepage && $hasHomepage >= 1) {
                // ASKMARC -> if we change all is_homepage to false, we must set the slug of these entries. But this could create errors of url validation.
                // so it's why I think we should keep an exception and let the user change the is_homepage to the other entry.
                throw new EntryException(self::HOMEPAGE_ALREADY_EXISTS);
            }
            // TODO check if url is available
            $newUrl = $this->getRelativeUrl($slug, $isHomepage);
            $content = self::findByURL($newUrl, false);
            if ($content) {
                throw new EntryException(sprintf(self::URL_NOT_AVAILABLE, $newUrl));
            }
        }

        if (!$isHomepage && !$slug) {
            throw new EntryException(self::SLUG_MISSING);
        }
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
     * @param  string              $locale
     * @param  bool                $isHomepage
     * @param  EntryStatus|string  $status
     * @param  string              $title
     * @param  string|null         $slug
     * @param  array|Collection    $optionalData
     * @return array|Entry|null
     * @throws DatabaseException
     * @throws EntryException
     * @throws ACLException
     *
     */
    public function createOne(string $locale, bool $isHomepage, EntryStatus|string $status, string $title, ?string $slug = null, array|Collection $optionalData = []): array|Entry|null
    {
        // TODO If author is set, the current user and author must have permission (?)
        $this->hasPermission();

        if ($status instanceof EntryStatus) {
            $status = $status->value;
        }

        $data = new Collection([
            'locale' => $locale,
            'is_homepage' => $isHomepage,
            'title' => $title,
            'status' => $status,
            'slug' => $slug
        ]);

        // Add the optional data to the creation
        if (is_array($optionalData)) {
            $data->merge(new Collection($optionalData));
        }

        return $this->create($data);
    }

    public function updateById(Entry|string $entry, array|Collection $data): bool
    {
        $this->hasPermission();

        if (is_string($entry)) {
            $entry = $this->findById($entry);
        }
        if (is_array($data)) {
            $data = new Collection($data);
        }

        return $this->update($entry, $data);
    }

    /**
     *
     * Delete an entry in soft mode or definitively
     *
     * @param  string  $entryId
     * @param  bool    $soft
     * @return bool
     * @throws EntryException
     *
     */
    public function delete(string $entryId, bool $soft = true): bool
    {
        $this->hasPermission();

        if ($soft) {
            $entry = $this->findById($entryId);
            $result = $this->softDelete($entry);
        } else {
            $result = $this->hardDelete($entryId);
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
            "authors" => new Authors($value->created_by, $value->created_by, $value->created_by, $value->created_by),
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
     * Check if current user has permission
     *
     * @return void
     * @throws DatabaseException
     * @throws EntryException
     * @throws ACLException
     *
     */
    private function hasPermission(): void
    {
//        if (!ACL::hasPermission(User::$currentUser, ACL::write($this->entryType->handle))) {
//            throw new EntryException(self::CANNOT_CREATE_ENTRY);
//        }
    }

    /**
     *
     * Get the relative url of the entry will be saved
     *
     * @param  ?string  $slug
     * @param  bool     $isHomepage
     * @return string
     *
     */
    private function getRelativeUrl(?string $slug, bool $isHomepage): string
    {
        $relativeUrl = "";
        if ($isHomepage) {
            $relativeUrl = "/";
        } else {
            if ($this->entryType->url_prefix) {
                $relativeUrl .= $this->entryType->url_prefix . '/';
            }
            $relativeUrl .= $slug;
        }
        return $relativeUrl;
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
        $is_homepage = $data->get('is_homepage');
        $status = $data->get('status', EntryStatus::INACTIVE->value);
        $title = $data->get('title');
        $slug = $data->get('slug');
        $author = User::$currentUser; // Handle permission and possible value from the call
        // TODO implements others fields: categories content alternates site_id

        // Test if url of the entry is available and if there is another isHomepage.
        $this->validateUrlAvailability($status, $is_homepage, $slug);

        $published = false;
        if ($status == EntryStatus::LIVE->value) {
            $published = true;
        }

        $dates = Dates::init($published);
        $authors = Authors::init($author, $published);

        try {
            $entryId = $this->insert([
                'entry_type_id' => (string)$this->entryType->_id,
                'locale' => $locale,
                'is_homepage' => $is_homepage,
                'status' => $status,
                'title' => $title,
                'slug' => $slug,
                'url' => $this->getRelativeUrl($slug, $is_homepage),
                'authors' => $authors,
                'dates' => $dates,
                // TODO
                'parent_id' => null,
                'site_id' => Sail::siteId(),
                'alternates' => new Collection([]),
                'categories' => new Collection([]),
                'content' => new Collection([])
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'creating') . PHP_EOL . $exception->getMessage());
        }

        return $this->findById($entryId)->exec();
    }

    /**
     *
     * Update an entry
     *
     * @param  Entry       $entry
     * @param  Collection  $data
     * @return bool
     * @throws EntryException
     *
     */
    private function update(Entry $entry, Collection $data): bool
    {
        $status = $data->get('status', $entry->status);
        $is_homepage = $data->get('is_homepage', $entry->is_homepage); // TODO Test if false
        $slug = $data->get('slug', $entry->slug);                      // TODO test if null

        $this->validateUrlAvailability($status, $is_homepage, $slug, $entry->_id);

        $update = [];
        $data->each(function ($key, $value) use (&$update)
        {
            if (in_array($key, ['parent_id', 'site_id', 'locale', 'is_homepage', 'status', 'title', 'slug', 'categories', 'content'])) {
                $update[$key] = $value;
            }
        });

        // Automatic attributes
        // TODO generate alternates
        $update['url'] = $this->getRelativeUrl($slug, $is_homepage);
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
    private function hardDelete(string $entryTypeId): bool
    {
        try {
            $qtyDeleted = $this->deleteById($entryTypeId);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'hard deleting') . PHP_EOL . $exception->getMessage());
        }

        return $qtyDeleted === 1;
    }
}
