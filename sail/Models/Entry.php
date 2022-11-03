<?php

namespace SailCMS\Models;

use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Http\Request;
use SailCMS\Types\Authors;
use SailCMS\Types\Dates;
use SailCMS\Types\EntryStatus;

class Entry extends BaseModel
{
    /* Errors */
    const TITLE_MISSING = "You must set the entry title in your data";
    const SLUG_MISSING = "You must set the entry slug in your data";
    const HOMEPAGE_ALREADY_EXISTS = "Your project has already an homepage that is live";
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

    private EntryType $_entryType;

    // TODO: populate CONTENT

    /**
     * @throws DatabaseException
     * @throws EntryException
     */
    public function __construct(string $collection = '')
    {
        // Get or create the default entry type
        if (!$collection) {
            $this->_entryType = EntryType::getDefaultType();
        } else {
            // Get entry type by collection name
            $this->_entryType = EntryType::getByCollectionName($collection);
        }

        $this->entry_type_id = $this->_entryType->_id;
        $collection = $this->_entryType->collection_name;

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
     * @return Entry|null
     * @throws DatabaseException|EntryException
     *
     */
    public static function findByURL(string $url): ?Entry
    {
        // Load all entry types before scanning them
        $availableTypes = EntryType::getAll();
        $request = new Request();
        $content = null;

        $availableTypes->each(function ($key, $value) use ($url, $request, &$content)
        {
            // We already have it, stop!
            if ($content !== null) {
                return;
            }

            // Search for what collection has this url (if any)
            $entry = new Entry($value->collection_name);
            $found = $entry->count(['url' => $url]);

            if ($found > 0) {
                // Winner Winner Chicken Diner!
                $content = $entry->findOne(['url' => $url])->exec();

                $preview = $request->get('pm', false, null);
                $previewVersion = $request->get('pv', false, null);

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
     *
     */
    public function one($filters): Entry|null
    {
        // TODO what to do with options

        return $this->findOne($filters)->exec();
    }

    public function all($filters, $limit, $offset): array
    {
        // Filters available date, author, category, status
        return [];
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
     *
     */
    public function create(string $locale, bool $isHomepage, EntryStatus|string $status, string $title, ?string $slug = null, array|Collection $optionalData = []): array|Entry|null
    {
        // TODO If author is set, the current user and author must have permission (?)
        $this->_hasPermission();

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

        // Add the optional data to the creations
        if (is_array($optionalData)) {
            $data->merge(new Collection($optionalData));
        }

        return $this->_create($data);
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
        $this->_hasPermission();

        if ($soft) {
            $entry = $this->findById($entryId);
            $result = $this->_softDelete($entry);
        } else {
            $result = $this->_hardDelete($entryId);
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

    private function _hasPermission(): void
    {
        // Put ACL
        // TODO add ACL dynamically
    }

    /**
     * @throws EntryException
     */
    private function _validUrlDataOnSave(Collection $data)
    {
        $isHomepage = $data->get('is_homepage');
        $slug = $data->get('slug');
        $status = $data->get('status');

        if ($status == EntryStatus::LIVE->value) {
            $hasHomepage = $this->count(['is_homepage' => true, 'status' => EntryStatus::LIVE->value]);

            if ($isHomepage && $hasHomepage >= 1) {
                throw new EntryException(self::HOMEPAGE_ALREADY_EXISTS);
            }
        }

        if (!$isHomepage && !$slug) {
            throw new EntryException(self::SLUG_MISSING);
        }
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
    private function _getRelativeUrl(?string $slug, bool $isHomepage): string
    {
        $relativeUrl = "";
        if ($isHomepage) {
            $relativeUrl = "/";
        } else {
            if ($this->_entryType->url_prefix) {
                $relativeUrl .= $this->_entryType->url_prefix . '/';
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
    private function _create(Collection $data): array|Entry|null
    {
        // Test if url of the entry is available and if there is another isHomepage.
        $this->_validUrlDataOnSave($data);

        $locale = $data->get('locale');
        $is_homepage = $data->get('is_homepage');
        $status = $data->get('status', EntryStatus::INACTIVE->value);
        $title = $data->get('title');
        $slug = $data->get('slug');
        $author = User::$currentUser; // Handle permission and possible value from the call
        // TODO implements others fields: categories content alternates site_id

        $published = false;
        if ($status == EntryStatus::LIVE->value) {
            $published = true;
        }

        $dates = Dates::init($published);
        $authors = Authors::init($author, $published);

        try {
            $entryId = $this->insert([
                'entry_type_id' => (string)$this->_entryType->_id,
                'locale' => $locale,
                'is_homepage' => $is_homepage,
                'status' => $status,
                'title' => $title,
                'slug' => $slug,
                'url' => $this->_getRelativeUrl($slug, $is_homepage),
                'authors' => $authors,
                'dates' => $dates,
                // TODO
                'parent_id' => null,
                'site_id' => null,
                'alternates' => new Collection([]),
                'categories' => new Collection([]),
                'content' => new Collection([])
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'creating') . PHP_EOL . $exception->getMessage());
        }

        return $this->findById($entryId)->exec();
    }

    private function _update(Collection $data)
    {
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
    private function _softDelete(Entry $entry): bool
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
    private function _hardDelete(string $entryTypeId): bool
    {
        try {
            $qtyDeleted = $this->deleteById($entryTypeId);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'hard deleting') . PHP_EOL . $exception->getMessage());
        }

        return $qtyDeleted === 1;
    }
}

//    /**
//     * Get or create an entry
//     *  nb: almost only for test
//     *
//     * @param  Collection  $data
//     * @return array|Entry|null
//     * @throws DatabaseException
//     */
//    public function getOrCreate(Collection $data): array|Entry|null
//    {
//        $entry = null;
//        $slug = $data->get('slug');
//        if (isset($slug)) {
//            $entry = $this->getBySlug($slug);
//        }
//
//        if (!$entry) {
//            $entry = $this->_create($data);
//        }
//        return $entry;
//    }