<?php

namespace SailCMS\Models;

use JetBrains\PhpStorm\Pure;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
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
    // Fields (content)

    // Entry Type id
    public string $entryTypeId;

    /**
     * Construct a new entry and set the collection name with the entry type
     *
     * @param  EntryType  $entryType
     */
    public function __construct(EntryType $entryType) {
        $this->entryTypeId = $entryType->_id;
        $collection = $entryType->handle;
        parent::__construct($collection);
    }

    /**
     * @param  bool  $fetchAllFields
     * @return string[]
     */
    public function fields(bool $fetchAllFields = false): array
    {
        // TODO add parent id and site id...
        return ['_id', 'entry_type_id', 'title', 'slug', 'authors', 'dates', 'categories'];
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
            "authors" => new Authors($value->createdBy, $value->updatedBy, $value->publishedBy, $value->deletedBy),
            "dates" => new Dates($value->created, $value->updated, $value->published, $value->deleted),
            default => $value,
        };
    }
}