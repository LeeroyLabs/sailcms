<?php

namespace SailCMS\Models;

use SailCMS\Collection;
use SailCMS\Database\BaseModel;

class EntryTypeLayout extends BaseModel
{
    public string $title;
    public Collection $content;

    public function fields(bool $fetchAllFields = false): array
    {
        return ['_id', 'title'];
    }
}