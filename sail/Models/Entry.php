<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Http\Request;
use SailCMS\Text;
use SailCMS\Types\Authors;
use SailCMS\Types\Dates;
use SailCMS\Types\EntryStatus;

class Entry extends BaseModel
{
    /* Fields */
    public string $title;
    public string $slug;
    public string $site_id;
    public string $parent_id;
    public string $locale;
    public Collection $alternates; // Array object "locale" -> "lang_code", "entry" -> "entry_id"
    public Authors $authors;
    public Dates $dates;
    public string $status;
    public Collection $categories;
    public Collection $content;

    /* Errors */
    const TITLE_MISSING_IN_COLLECTION = "You must set the entry type title in your data collection";

    // TODO: populate CONTENT

    public function __construct(string $collection = '')
    {
        // Determine the type used with the collection name (?)

        parent::__construct($collection);
    }

    public function fields(bool $fetchAllFields = false): array
    {
        return [
            '_id',
            'parent_id',
            'site_id',
            'locale',
            'title',
            'slug',
            'status',
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
     * @throws DatabaseException
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

    public function all($filters, $limit, $offset): EntryType|null
    {
        // Filters available date, author, category, status
    }

    public function create()
    {
        // Put ACL
        // TODO add ACL dynamically
        $this->_create();
    }

    protected function processOnFetch(string $field, mixed $value): mixed
    {
        return $value;
//        return match ($field) {
//            "status" => EntryStatus::getStatusEnum($value),
//            "authors" => new Authors($value->createdBy, $value->updatedBy, $value->publishedBy, $value->deletedBy),
//            "dates" => new Dates($value->created, $value->updated, $value->published, $value->deleted),
//            default => $value,
//        };
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
            throw new EntryException(self::TITLE_MISSING_IN_COLLECTION);
        }

        return parent::processOnStore($field, $value);
    }

    /**
     *
     * @param  Collection  $data
     * @return array|Entry|null
     * @throws DatabaseException
     *
     */
    private function _create(Collection $data): array|Entry|null
    {
        // TODO test if url of the entry is available.

        $title = $data->get('title');
        $slug = $data->get('slug');
        $status = $data->get('status', EntryStatus::INACTIVE);
        // TODO implements others fields: categories content

        $published = false;
        if ($status->value == EntryStatus::LIVE) {
            $published = true;
        }

        $currentUser = User::$currentUser;
        if (!$currentUser) {
            $currentUser = new User();
            $currentUser->_id = new ObjectId();
            $currentUser->email = "code@test.com";
        }
        $dates = Dates::init($published);
        $authors = Authors::init($currentUser, $published);

        if (!isset($slug)) {
            $slug = Text::slugify($title);
        }

        $entryId = $this->insert([
            'title' => $title,
            'slug' => $slug,
            'status' => $status->value,
            'authors' => $authors,
            'dates' => $dates,
            'categories' => new Collection([]),
            'content' => new Collection([])
        ]);

        return $this->findById($entryId)->exec();
    }

    private function _update(Collection $data)
    {
    }

    private function _delete(string $entryTypeId)
    {
    }
}

//    /**
//     * Construct a new entry and set the collection name with the entry type
//     *
//     * @param  ?EntryType  $entryType
//     */
//    public function __construct(?EntryType $entryType = null)
//    {
//        $collection = "Entry";
//        if ($entryType) {
//            $this->entryTypeId = $entryType->_id;
//            $collection = $entryType->handle;
//        }
//        parent::__construct($collection);
//    }

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