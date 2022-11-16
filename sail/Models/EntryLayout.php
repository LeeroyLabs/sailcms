<?php

namespace SailCMS\Models;

use SailCMS\Collection;
use SailCMS\Database\Model;

class EntryLayout extends Model
{
    public string $title;
    public Collection $schema;

    const LAYOUT_TITLE_SUFFIX = "layout";

    public function fields(bool $fetchAllFields = false): array
    {
        return ['_id', 'title', 'schema'];
    }

    // Export/import of layout json (pretty)
}