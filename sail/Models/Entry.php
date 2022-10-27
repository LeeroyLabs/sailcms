<?php

namespace SailCMS\Models;

use JetBrains\PhpStorm\Pure;
use MongoDB\BSON\ObjectId;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\DatabaseException;
use SailCMS\Text;
use SailCMS\Types\Authors;
use SailCMS\Types\Dates;
use SailCMS\Types\EntryStatus;

class Entry extends BaseModel
{
    const TITLE_MISSING_IN_COLLECTION = "You must set the entry type title in your data collection";

    public string $title;
    public string $slug;
    // Site id
    // Parent id
    public EntryStatus $status;
    public Authors $authors;
    public Dates $dates;
    public Collection $categories;

    // Entry Type id
    public Collection $content;
    public string $entryTypeId;

    /**
     * Construct a new entry and set the collection name with the entry type
     *
     * @param  ?EntryType  $entryType
     */
    public function __construct(?EntryType $entryType = null)
    {
        $collection = "Entry";
        if ($entryType) {
            $this->entryTypeId = $entryType->_id;
            $collection = $entryType->handle;
        }
        parent::__construct($collection);
    }

    /**
     * @param  bool  $fetchAllFields
     * @return string[]
     */
    public function fields(bool $fetchAllFields = false): array
    {
        // TODO add parent id and site id...
        return ['_id', 'entry_type_id', 'title', 'slug', 'status', 'authors', 'dates', 'categories', 'content'];
    }

    /**
     * Get or create an entry
     *  nb: almost only for test
     *
     * @param  Collection  $data
     * @return array|Entry|null
     * @throws DatabaseException
     */
    public function getOrCreate(Collection $data): array|Entry|null
    {
        $entry = null;
        $slug = $data->get('slug');
        if (isset($slug)) {
            $entry = $this->getBySlug($slug);
        }

        if (!$entry) {
            $entry = $this->_create($data);
        }
        return $entry;
    }

    /**
     * Get an entry by slug
     *
     * @param  string  $slug
     * @return array|Entry|null
     * @throws DatabaseException
     */
    public function getBySlug(string $slug): array|Entry|null
    {
        // TODO add status!
        return $this->findOne(['slug' => $slug])->exec();
    }

    public function createFromAPI()
    {
        // Put ACL
        // TODO add ACL dynamically
    }

    /**
     * Create an entry
     *
     * @param  Collection  $data
     * @return array|Entry|null
     * @throws DatabaseException
     */
    private function _create(Collection $data): array|Entry|null
    {
        // TODO test if url of the entry is available.

        $title = $data->get('title');
        $slug = $data->get('slug');
        $status = $data->get('status', EntryStatus::INACTIVE);
        // TODO implements others fields

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
        $dates = Dates::atCreation($published);
        $authors = Authors::atCreation($currentUser, $published);

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

    /**
     * Process fields normally excepts authors and dates
     *
     * @param  string  $field
     * @param  mixed   $value
     * @return mixed
     */
    #[Pure]
    protected function processOnFetch(string $field, mixed $value): mixed
    {
        return match ($field) {
            "status" => EntryStatus::getStatusEnum($value),
            "authors" => new Authors($value->createdBy, $value->updatedBy, $value->publishedBy, $value->deletedBy),
            "dates" => new Dates($value->created, $value->updated, $value->published, $value->deleted),
            default => $value,
        };
    }
}