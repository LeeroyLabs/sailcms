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
    public EntryType $entryType;

    public function __construct(string $collection = '') {
        parent::__construct($collection);
    }

    public function fields(bool $fetchAllFields = false): array
    {
        return ['_id', 'title', 'slug', 'authors', 'dates', 'categories'];
    }

    protected function processOnFetch(string $field, mixed $value): mixed
    {
        return match ($field) {
            "authors" => new Authors($value->createdBy, $value->updatedBy, $value->publishedBy, $value->deletedBy),
            "dates" => new Dates($value->created, $value->updated, $value->published, $value->deleted),
            default => $value,
        };
    }
}