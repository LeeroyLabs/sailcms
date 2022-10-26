<?php

namespace SailCMS\Models;

use JetBrains\PhpStorm\Pure;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Text;
use SailCMS\Types\Authors;
use SailCMS\Types\Dates;
use SailCMS\Types\EntryStatus;

class Entry extends BaseModel
{
    public string $title;
    public string $slug;
    public EntryStatus $status;
    // Site id
    // Parent id
    public Authors $authors;
    public Dates $dates;
    public Collection $categories;
    public Collection $content;

    // Entry Type id
    public string $entryTypeId;

    const TITLE_MISSING_IN_COLLECTION = "You must set the entry type title in your data collection";

    /**
     * Construct a new entry and set the collection name with the entry type
     *
     * @param  ?EntryType  $entryType
     */
    public function __construct(?EntryType $entryType=null) {
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
        return $this->findOne(['slug'=>$slug])->exec();
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
            "status" => EntryStatus::INACTIVE,
            "authors" => new Authors($value->createdBy, $value->updatedBy, $value->publishedBy, $value->deletedBy),
            "dates" => new Dates($value->created, $value->updated, $value->published, $value->deleted),
            default => $value,
        };
    }

    /**
     * Create an entry
     *
     * @param  Collection  $data
     * @return array|Entry|null
     * @throws DatabaseException
     */
    private function _create(Collection $data): array|Entry|null {
        $title = $data->get('title');
        $slug = $data->get('slug');
        // TODO implements others fields
        $status = EntryStatus::INACTIVE;
        $authors = new Authors('','','','');

        if (!isset($slug)) {
            $slug = Text::slugify($title);
        }

        $entryId = $this->insert([
            'title' => $title,
            'slug' => $slug,
            'status' => $status->value,
            'authors' => $authors->toArray(),
            'dates' => Dates::atCreation(),
            'categories' => new Collection([]),
            'content' => new Collection([])
        ]);

        return $this->findById($entryId)->exec();
    }
}